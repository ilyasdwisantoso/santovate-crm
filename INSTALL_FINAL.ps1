$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

# IMPORTANT: PowerShell's provider location and .NET's process working directory
# can differ on Windows. Always anchor every file operation to the script folder.
$ProjectRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
Set-Location -LiteralPath $ProjectRoot
[Environment]::CurrentDirectory = $ProjectRoot

function Project-Path([string]$RelativePath) {
    return [System.IO.Path]::Combine($ProjectRoot, ($RelativePath -replace '^[.][\\/]', ''))
}

Write-Host ""
Write-Host "======================================================" -ForegroundColor DarkGreen
Write-Host "   SANTOVATE CRM 2.1.1 FINAL - FRESH INSTALL" -ForegroundColor Green
Write-Host "======================================================" -ForegroundColor DarkGreen
Write-Host "Project root: $ProjectRoot" -ForegroundColor DarkGray
Write-Host "Installer ini tidak menghapus database CRM lama secara otomatis." -ForegroundColor DarkGray
Write-Host ""

function Require-Command([string]$Name, [string]$Message) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) { throw $Message }
}

function Run-Native([string]$Label, [scriptblock]$Command) {
    Write-Host "`n$Label" -ForegroundColor Cyan
    $global:LASTEXITCODE = 0
    & $Command
    $code = $LASTEXITCODE
    if ($null -eq $code) { $code = 0 }
    if ($code -ne 0) {
        throw "$Label gagal (exit code $code). Installer dihentikan agar tidak memberi status sukses palsu."
    }
}

function Read-Default([string]$Prompt, [string]$Default) {
    $value = Read-Host "$Prompt [$Default]"
    if ([string]::IsNullOrWhiteSpace($value)) { return $Default }
    return $value.Trim()
}

function Set-EnvValue([string]$Path, [string]$Key, [string]$Value) {
    if (-not [System.IO.Path]::IsPathRooted($Path)) { $Path = Project-Path $Path }
    $content = [System.IO.File]::ReadAllText($Path)
    if ($null -eq $Value) { $Value = "" }
    $escaped = $Value.Replace('"', '\"')
    $line = "$Key=`"$escaped`""
    $pattern = "(?m)^" + [Regex]::Escape($Key) + "=.*$"
    if ([Regex]::IsMatch($content, $pattern)) {
        $content = [Regex]::Replace($content, $pattern, $line)
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $content, $utf8NoBom)
}

function Remove-Utf8Bom([string]$Path) {
    if (-not [System.IO.Path]::IsPathRooted($Path)) { $Path = Project-Path $Path }
    if (-not (Test-Path -LiteralPath $Path)) { return }
    $bytes = [System.IO.File]::ReadAllBytes($Path)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $clean = New-Object byte[] ($bytes.Length - 3)
        [Array]::Copy($bytes, 3, $clean, 0, $clean.Length)
        [System.IO.File]::WriteAllBytes($Path, $clean)
    }
}

$packageJson = Project-Path "package.json"
$composerJson = Project-Path "composer.json"
$envExample = Project-Path ".env.example"
$envFile = Project-Path ".env"
$artisan = Project-Path "artisan"
$createDatabaseScript = Project-Path "scripts\create_database.php"
$viteManifest = Project-Path "public\build\manifest.json"

foreach ($requiredFile in @($packageJson, $composerJson, $envExample, $artisan, $createDatabaseScript)) {
    if (-not (Test-Path -LiteralPath $requiredFile)) {
        throw "File project wajib tidak ditemukan: $requiredFile`nPastikan ZIP diekstrak lengkap dan INSTALL_FINAL.ps1 berada di root project."
    }
}

Require-Command "php" "PHP tidak ditemukan di PATH. Aktifkan PHP XAMPP/Composer terlebih dahulu."
Require-Command "composer" "Composer tidak ditemukan di PATH. Install Composer terlebih dahulu."
Require-Command "node" "Node.js tidak ditemukan. Install Node.js terlebih dahulu."
Require-Command "npm" "npm tidak ditemukan. Install Node.js yang menyertakan npm."

Run-Native "[CHECK] Memeriksa PHP >= 8.2" { php -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);" }
Run-Native "[CHECK] Memeriksa extension PDO MySQL" { php -r "exit(extension_loaded('pdo_mysql')?0:1);" }

$nodeVersion = (& node -p "process.versions.node").Trim()
$nodeMajor = [int]($nodeVersion.Split('.')[0])
$nodeMinor = [int]($nodeVersion.Split('.')[1])
if (-not (($nodeMajor -eq 20 -and $nodeMinor -ge 19) -or ($nodeMajor -ge 22 -and -not ($nodeMajor -eq 22 -and $nodeMinor -lt 12)))) {
    throw "Node.js $nodeVersion tidak didukung Vite 8. Gunakan Node 20.19+ atau 22.12+ (termasuk Node 24)."
}
Write-Host "Node.js $nodeVersion OK." -ForegroundColor Green

$requiredDirs = @(
    "bootstrap\cache",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\logs",
    "storage\app\imports\tmp",
    "storage\app\templates"
)
foreach ($dir in $requiredDirs) {
    $fullDir = Project-Path $dir
    if (-not (Test-Path -LiteralPath $fullDir)) { New-Item -ItemType Directory -Force -Path $fullDir | Out-Null }
}

# JSON files must be UTF-8 without BOM. This prevents Vite/PostCSS JSON parse errors on Windows.
Remove-Utf8Bom $packageJson
Remove-Utf8Bom $composerJson

if (-not (Test-Path -LiteralPath $envFile)) {
    Copy-Item -LiteralPath $envExample -Destination $envFile
}
Remove-Utf8Bom $envFile

Write-Host ""
Write-Host "Konfigurasi database MySQL/MariaDB" -ForegroundColor Yellow
$dbHost = Read-Default "DB Host" "127.0.0.1"
$dbPort = Read-Default "DB Port (XAMPP biasanya 3306; lingkungan Anda bisa 3307)" "3306"
$dbName = Read-Default "Nama database baru" "santovate_crm_final"
$dbUser = Read-Default "DB Username" "root"
$dbPass = Read-Host "DB Password [kosong jika root tanpa password]"
if ($null -eq $dbPass) { $dbPass = "" }

if ($dbPort -notmatch '^\d+$') { throw "DB Port harus berupa angka." }
if ($dbName -notmatch '^[A-Za-z0-9_]+$') { throw "Nama database hanya boleh huruf, angka, dan underscore." }

Set-EnvValue $envFile "DB_CONNECTION" "mysql"
Set-EnvValue $envFile "DB_HOST" $dbHost
Set-EnvValue $envFile "DB_PORT" $dbPort
Set-EnvValue $envFile "DB_DATABASE" $dbName
Set-EnvValue $envFile "DB_USERNAME" $dbUser
Set-EnvValue $envFile "DB_PASSWORD" $dbPass
Set-EnvValue $envFile "APP_URL" "http://127.0.0.1:8000"

$env:SANTOVATE_DB_HOST = $dbHost
$env:SANTOVATE_DB_PORT = $dbPort
$env:SANTOVATE_DB_NAME = $dbName
$env:SANTOVATE_DB_USER = $dbUser
$env:SANTOVATE_DB_PASS = $dbPass
try {
    Run-Native "[1/10] Memastikan database tersedia" { php $createDatabaseScript }
} finally {
    Remove-Item Env:SANTOVATE_DB_HOST -ErrorAction SilentlyContinue
    Remove-Item Env:SANTOVATE_DB_PORT -ErrorAction SilentlyContinue
    Remove-Item Env:SANTOVATE_DB_NAME -ErrorAction SilentlyContinue
    Remove-Item Env:SANTOVATE_DB_USER -ErrorAction SilentlyContinue
    Remove-Item Env:SANTOVATE_DB_PASS -ErrorAction SilentlyContinue
}

Run-Native "[2/10] Menginstall dependency PHP" { composer install --no-interaction --prefer-dist }

# Composer/editors can recreate text files differently on Windows; normalize JSON once more before npm.
Remove-Utf8Bom $packageJson
Run-Native "[3/10] Menginstall dependency React/Vite" { npm install --no-audit }
Run-Native "[4/10] Build frontend production React" { npm run build }

if (-not (Test-Path -LiteralPath $viteManifest)) {
    throw "Build selesai tetapi public/build/manifest.json tidak ditemukan: $viteManifest"
}
Write-Host "Manifest Vite ditemukan." -ForegroundColor Green

Run-Native "[5/10] Membuat APP_KEY" { php artisan key:generate --force }
Run-Native "[6/10] Membersihkan cache Laravel" { php artisan optimize:clear }
Run-Native "[7/10] Menjalankan migration + seeder" { php artisan migrate --seed --force }
Run-Native "[8/10] Verifikasi route Laravel" { php artisan route:list --except-vendor }
Run-Native "[9/10] Verifikasi aplikasi Laravel" { php artisan about }
Run-Native "[10/10] Menjalankan unit test inti" { php artisan test --testsuite=Unit }

Write-Host ""
Write-Host "======================================================" -ForegroundColor DarkGreen
Write-Host "           INSTALL SANTOVATE CRM BERHASIL" -ForegroundColor Green
Write-Host "======================================================" -ForegroundColor DarkGreen
Write-Host "Project  : $ProjectRoot" -ForegroundColor White
Write-Host "Database : $dbName" -ForegroundColor White
Write-Host "Admin    : admin@santovate.local / Santovate123!" -ForegroundColor White
Write-Host "Sales    : sales@santovate.local / Sales123!" -ForegroundColor White
Write-Host ""
Write-Host "Jalankan aplikasi dengan:" -ForegroundColor Yellow
Write-Host ".\START_CRM.ps1" -ForegroundColor Cyan
Write-Host "Lalu buka http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Ganti password default setelah login pertama." -ForegroundColor Yellow
