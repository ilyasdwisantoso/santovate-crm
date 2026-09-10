$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
Set-Location -LiteralPath $ProjectRoot
[Environment]::CurrentDirectory = $ProjectRoot

$envFile = Join-Path $ProjectRoot ".env"
$manifest = Join-Path $ProjectRoot "public\build\manifest.json"

if (-not (Test-Path -LiteralPath $envFile)) { throw ".env tidak ditemukan. Jalankan INSTALL_FINAL.ps1 terlebih dahulu." }
if (-not (Test-Path -LiteralPath $manifest)) { throw "Frontend belum dibuild. Jalankan INSTALL_FINAL.ps1 atau npm run build." }

Write-Host "Santovate CRM running from: $ProjectRoot" -ForegroundColor Green
php artisan serve --host=127.0.0.1 --port=8000
