param(
    [string]$SshUser = "u354235361",
    [string]$SshHost = "77.37.81.70",
    [int]$SshPort = 65002,
    [string]$KeyPath = "$env:USERPROFILE\.ssh\santovate_deploy_ed25519"
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

foreach ($cmd in @('ssh','ssh-keygen')) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        throw "Command tidak ditemukan: $cmd"
    }
}

$keyDir = Split-Path -Parent $KeyPath
if (-not (Test-Path -LiteralPath $keyDir)) {
    New-Item -ItemType Directory -Force -Path $keyDir | Out-Null
}

if (-not (Test-Path -LiteralPath $KeyPath)) {
    Write-Host "Membuat SSH deploy key..." -ForegroundColor Cyan
    & ssh-keygen -t ed25519 -f $KeyPath -N "" -C "santovate-deploy"
    if ($LASTEXITCODE -ne 0) { throw "ssh-keygen gagal." }
}

$pubPath = "$KeyPath.pub"
if (-not (Test-Path -LiteralPath $pubPath)) { throw "Public key tidak ditemukan: $pubPath" }
$pub = (Get-Content -LiteralPath $pubPath -Raw).Trim()
$escaped = $pub.Replace("'", "'\"'\"'")

Write-Host "Memasang public key ke Hostinger. Password SSH mungkin diminta satu kali." -ForegroundColor Yellow
$remote = "umask 077; mkdir -p ~/.ssh; touch ~/.ssh/authorized_keys; grep -qxF '$escaped' ~/.ssh/authorized_keys 2>/dev/null || echo '$escaped' >> ~/.ssh/authorized_keys; chmod 700 ~/.ssh; chmod 600 ~/.ssh/authorized_keys"
& ssh -p $SshPort "$SshUser@$SshHost" $remote
if ($LASTEXITCODE -ne 0) { throw "Gagal memasang SSH key." }

Write-Host "Menguji login tanpa password..." -ForegroundColor Cyan
& ssh -i $KeyPath -p $SshPort -o BatchMode=yes "$SshUser@$SshHost" "echo SSH_KEY_OK"
if ($LASTEXITCODE -ne 0) { throw "SSH key terpasang tetapi test BatchMode gagal." }

Write-Host "SSH key siap: $KeyPath" -ForegroundColor Green
