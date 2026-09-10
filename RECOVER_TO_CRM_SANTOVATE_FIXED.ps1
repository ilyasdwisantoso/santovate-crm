$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$ProjectRoot = $PSScriptRoot
if (-not $ProjectRoot) { $ProjectRoot = (Get-Location).Path }
$ProjectRoot = [System.IO.Path]::GetFullPath($ProjectRoot)
Set-Location -LiteralPath $ProjectRoot
[Environment]::CurrentDirectory = $ProjectRoot

$artisan = Join-Path $ProjectRoot 'artisan'
$envFile = Join-Path $ProjectRoot '.env'
$dbScript = Join-Path $ProjectRoot 'scripts\create_database.php'

foreach ($f in @($artisan, $envFile, $dbScript)) {
    if (-not (Test-Path -LiteralPath $f)) { throw "File wajib tidak ditemukan: $f" }
}

function Get-EnvValue([string]$Key, [string]$Default = '') {
    $content = [System.IO.File]::ReadAllText($envFile)
    $m = [regex]::Match($content, '(?m)^' + [regex]::Escape($Key) + '=(.*)$')
    if (-not $m.Success) { return $Default }
    return $m.Groups[1].Value.Trim().Trim('"').Trim("'")
}

function Set-EnvValue([string]$Key, [string]$Value) {
    $content = [System.IO.File]::ReadAllText($envFile)
    $escaped = $Value.Replace('"','\"')
    $line = "$Key=`"$escaped`""
    $pattern = '(?m)^' + [regex]::Escape($Key) + '=.*$'
    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, $line)
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($envFile, $content, $utf8NoBom)
}

function Run-Step([string]$Label, [scriptblock]$Command) {
    Write-Host "`n$Label" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label gagal (exit code $LASTEXITCODE)." }
}

$targetDb = 'crm_santovate'
$dbHost = Get-EnvValue 'DB_HOST' '127.0.0.1'
$dbPort = Get-EnvValue 'DB_PORT' '3306'
$dbUser = Get-EnvValue 'DB_USERNAME' 'root'
$dbPass = Get-EnvValue 'DB_PASSWORD' ''
$oldDb = Get-EnvValue 'DB_DATABASE' ''

Write-Host '======================================================' -ForegroundColor Green
Write-Host ' SANTOVATE CRM - RECOVERY KE crm_santovate' -ForegroundColor Green
Write-Host '======================================================' -ForegroundColor Green
Write-Host "Project : $ProjectRoot"
Write-Host "DB lama : $oldDb" -ForegroundColor DarkGray
Write-Host "DB baru : $targetDb" -ForegroundColor Yellow

# Create database if it does not exist.
$env:SANTOVATE_DB_HOST = $dbHost
$env:SANTOVATE_DB_PORT = $dbPort
$env:SANTOVATE_DB_NAME = $targetDb
$env:SANTOVATE_DB_USER = $dbUser
$env:SANTOVATE_DB_PASS = $dbPass
& php $dbScript
$createCode = $LASTEXITCODE
Remove-Item Env:SANTOVATE_DB_HOST -ErrorAction SilentlyContinue
Remove-Item Env:SANTOVATE_DB_PORT -ErrorAction SilentlyContinue
Remove-Item Env:SANTOVATE_DB_NAME -ErrorAction SilentlyContinue
Remove-Item Env:SANTOVATE_DB_USER -ErrorAction SilentlyContinue
Remove-Item Env:SANTOVATE_DB_PASS -ErrorAction SilentlyContinue
if ($createCode -ne 0) { throw "Gagal membuat/mengecek database $targetDb (exit code $createCode)." }

# Point Laravel at the target DB.
Set-EnvValue 'DB_DATABASE' $targetDb

# IMPORTANT: before migrations, database-backed cache/session/queue tables do not exist yet.
Set-EnvValue 'CACHE_STORE' 'file'
Set-EnvValue 'SESSION_DRIVER' 'file'
Set-EnvValue 'QUEUE_CONNECTION' 'sync'

Run-Step '[1/4] Membersihkan CONFIG cache tanpa menyentuh tabel cache' { php artisan config:clear }
Run-Step '[2/4] Menjalankan fresh migration + seeder' { php artisan migrate --seed --force }

# Now database-backed runtime tables exist, so restore production-like drivers.
Set-EnvValue 'CACHE_STORE' 'database'
Set-EnvValue 'SESSION_DRIVER' 'database'
Set-EnvValue 'QUEUE_CONNECTION' 'database'

Run-Step '[3/4] Reload konfigurasi setelah migration' { php artisan config:clear }
Run-Step '[4/4] Verifikasi Laravel' { php artisan about }

Write-Host ''
Write-Host 'RECOVERY BERHASIL' -ForegroundColor Green
Write-Host "Database aktif : $targetDb" -ForegroundColor White
Write-Host "Database lama  : $oldDb (tidak dihapus)" -ForegroundColor DarkGray
Write-Host 'Selanjutnya jalankan: .\START_CRM.ps1' -ForegroundColor Cyan
