#!/usr/bin/env bash
set -Eeuo pipefail

ARTIFACT="${1:?Build artifact .tar.gz wajib diberikan}"
BRANCH="${2:-master}"
PHP_BIN="${3:-/opt/alt/php84/usr/bin/php}"
BASE_URL="${4:-https://crm.santovate.com}"
COMPOSER_BIN="${SANTOVATE_COMPOSER_BIN:-composer2}"

PROJECT_ROOT="$(pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
NEXT_DIR="storage/app/deploy/build-next-${STAMP}"
PREV_DIR="public/build-prev-${STAMP}"
SWAPPED=0

log() { printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }
fail() { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

rollback_build() {
    if [[ "$SWAPPED" -eq 1 ]]; then
        log "Health check gagal. Rollback frontend build."
        rm -rf public/build
        if [[ -d "$PREV_DIR" ]]; then
            mv "$PREV_DIR" public/build
        fi
        "$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true
    fi
}
trap rollback_build ERR

[[ -f artisan ]] || fail "artisan tidak ditemukan. Jalankan script dari root project."
[[ -x "$PHP_BIN" ]] || fail "PHP binary tidak ditemukan/executable: $PHP_BIN"
command -v git >/dev/null 2>&1 || fail "git tidak tersedia"
command -v tar >/dev/null 2>&1 || fail "tar tidak tersedia"
command -v curl >/dev/null 2>&1 || fail "curl tidak tersedia"
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || fail "$COMPOSER_BIN tidak tersedia"
[[ -f "$ARTIFACT" ]] || fail "Artifact tidak ditemukan: $ARTIFACT"

mkdir -p storage/app/deploy
rm -rf "$NEXT_DIR"
mkdir -p "$NEXT_DIR"

log "Extract build artifact ke staging directory"
tar -xzf "$ARTIFACT" -C "$NEXT_DIR"

log "Verify build sebelum menyentuh production"
"$PHP_BIN" scripts/deploy/verify-vite-build.php "$NEXT_DIR"

log "Update source dari GitHub ($BRANCH)"
git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"

log "Install dependency PHP production"
"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

log "Migration incremental production"
"$PHP_BIN" artisan migrate --force

log "Clear Laravel cache sebelum swap"
"$PHP_BIN" artisan optimize:clear

log "Atomic frontend build swap"
if [[ -d public/build ]]; then
    mv public/build "$PREV_DIR"
fi
mv "$NEXT_DIR" public/build
SWAPPED=1
rm -f public/hot
chmod -R u+rwX,go+rX public/build

log "Verify build aktif"
"$PHP_BIN" scripts/deploy/verify-vite-build.php public/build

log "Refresh storage link dan cache"
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
"$PHP_BIN" artisan optimize:clear

log "HTTP health check"
LOGIN_CODE="$(curl -L -sS -o /dev/null -w '%{http_code}' "${BASE_URL%/}/login")"
case "$LOGIN_CODE" in
    200|301|302) ;;
    *) fail "Login health check HTTP $LOGIN_CODE" ;;
esac

ASSETS="$($PHP_BIN -r '
$m=json_decode(file_get_contents("public/build/manifest.json"),true);
foreach($m as $e){
 if(isset($e["file"])) echo $e["file"],PHP_EOL;
 foreach(($e["css"]??[]) as $c) echo $c,PHP_EOL;
}' | sort -u)"

while IFS= read -r asset; do
    [[ -n "$asset" ]] || continue
    code="$(curl -L -sS -o /dev/null -w '%{http_code}' "${BASE_URL%/}/build/${asset}")"
    if [[ "$code" != "200" ]]; then
        fail "Asset health check gagal HTTP $code: $asset"
    fi
    echo "HTTP 200 ${BASE_URL%/}/build/${asset}"
done <<< "$ASSETS"

log "Deployment berhasil"
SWAPPED=0
rm -f "$ARTIFACT"
rm -rf "$NEXT_DIR" 2>/dev/null || true

# Keep only 3 previous frontend builds.
mapfile -t backups < <(ls -dt public/build-prev-* 2>/dev/null || true)
if (( ${#backups[@]} > 3 )); then
    for old in "${backups[@]:3}"; do
        rm -rf "$old"
    done
fi

printf '\nDEPLOY_PRODUCTION_OK\n'
printf 'Branch: %s\n' "$BRANCH"
printf 'URL: %s\n' "$BASE_URL"
printf 'Build backup: %s\n' "$PREV_DIR"
