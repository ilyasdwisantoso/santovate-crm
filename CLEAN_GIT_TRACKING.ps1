param(
    [string]$ProjectPath = "C:\Projects\santovate-crm-final",
    [string]$Branch = "master",
    [switch]$Push
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectPath = [System.IO.Path]::GetFullPath($ProjectPath)
Set-Location -LiteralPath $ProjectPath
[Environment]::CurrentDirectory = $ProjectPath

if (-not (Test-Path -LiteralPath (Join-Path $ProjectPath ".git"))) {
    throw "Folder .git tidak ditemukan: $ProjectPath"
}

Write-Host "Membersihkan generated deployment artifacts dari Git index." -ForegroundColor Cyan
Write-Host "File lokal tidak dihapus; hanya tracking Git yang dihentikan." -ForegroundColor DarkGray

& git rm -r --cached --ignore-unmatch -- public/build _patch_backup
if ($LASTEXITCODE -ne 0) { throw "git rm cached gagal." }

$tracked = @(& git ls-files)
$artifactFiles = @($tracked | Where-Object {
    $_ -like 'Santovate-PUBLIC-BUILD-*.zip' -or
    $_ -like 'Santovate-PUBLIC-BUILD-*.tar.gz' -or
    $_ -like 'Santovate-CRM-*.zip' -or
    $_ -like 'storage/logs/*.log'
})

foreach ($file in $artifactFiles) {
    & git rm --cached --ignore-unmatch -- $file
    if ($LASTEXITCODE -ne 0) { throw "Gagal untrack: $file" }
}

& git add .gitignore DEPLOY_SANTOVATE.ps1 CLEAN_GIT_TRACKING.ps1 SETUP_SSH_KEY.ps1 scripts/deploy
if ($LASTEXITCODE -ne 0) { throw "git add deployment files gagal." }

Write-Host "`nStaged cleanup:" -ForegroundColor Yellow
& git diff --cached --name-status

& git commit -m "Automate Santovate production deployment"
$commitCode = $LASTEXITCODE
if ($commitCode -ne 0) {
    $staged = @(& git diff --cached --name-only)
    if ($staged.Count -gt 0) { throw "git commit gagal (exit code $commitCode)." }
    Write-Host "Tidak ada perubahan baru untuk di-commit." -ForegroundColor DarkGray
}

if ($Push) {
    & git push origin $Branch
    if ($LASTEXITCODE -ne 0) { throw "git push gagal." }
    Write-Host "Cleanup sudah di-push ke origin/$Branch." -ForegroundColor Green
} else {
    Write-Host "`nCleanup selesai. Review lalu jalankan:" -ForegroundColor Green
    Write-Host "git push origin $Branch" -ForegroundColor Cyan
}
