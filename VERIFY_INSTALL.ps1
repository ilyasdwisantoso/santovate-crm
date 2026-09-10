$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
Set-Location -LiteralPath $ProjectRoot
[Environment]::CurrentDirectory = $ProjectRoot

function P([string]$RelativePath) {
    return [System.IO.Path]::Combine($ProjectRoot, ($RelativePath -replace '^[.][\\/]', ''))
}

function Run-Check([string]$Label, [scriptblock]$Command) {
    Write-Host "[CHECK] $Label" -ForegroundColor Cyan
    $global:LASTEXITCODE = 0
    & $Command
    $code = $LASTEXITCODE
    if ($null -eq $code) { $code = 0 }
    if ($code -ne 0) { throw "$Label gagal (exit code $code)." }
}

if (-not (Test-Path -LiteralPath (P ".env"))) { throw ".env belum ada. Jalankan INSTALL_FINAL.ps1 terlebih dahulu." }
if (-not (Test-Path -LiteralPath (P "vendor\autoload.php"))) { throw "vendor belum ada. Jalankan INSTALL_FINAL.ps1." }
if (-not (Test-Path -LiteralPath (P "node_modules"))) { throw "node_modules belum ada. Jalankan INSTALL_FINAL.ps1." }
if (-not (Test-Path -LiteralPath (P "public\build\manifest.json"))) { throw "Build React belum ada. Jalankan npm run build." }

Run-Check "Laravel boot" { php artisan about --only=environment }
Run-Check "Database migrations" { php artisan migrate:status }
Run-Check "Routes" { php artisan route:list --except-vendor }
Write-Host "Semua pemeriksaan utama lolos. Project: $ProjectRoot" -ForegroundColor Green
