param(
    [string]$ProjectPath = "C:\Projects\santovate-crm-final",
    [string]$SshUser = "u354235361",
    [string]$SshHost = "77.37.81.70",
    [int]$SshPort = 65002,
    [string]$RemoteProjectPath = "/home/u354235361/domains/santovate.com/public_html/crm-santovate",
    [string]$Branch = "master",
    [string]$PhpBinary = "/opt/alt/php84/usr/bin/php",
    [string]$ComposerBinary = "composer2",
    [string]$BaseUrl = "https://crm.santovate.com",
    [string]$CommitMessage = "",
    [bool]$AutoCommit = $true,
    [switch]$UseNpmInstall,
    [string]$IdentityFile = ""
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Require-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Command tidak ditemukan: $Name"
    }
}

function Run-Native([string]$Label, [scriptblock]$Command) {
    Write-Host "`n[$Label]" -ForegroundColor Cyan
    $global:LASTEXITCODE = 0
    & $Command
    $code = $LASTEXITCODE
    if ($null -eq $code) { $code = 0 }
    if ($code -ne 0) { throw "$Label gagal (exit code $code)." }
}

function Shell-Quote([string]$Value) {
    return "'" + ($Value -replace "'", "'\"'\"'") + "'"
}

Require-Command "git"
Require-Command "node"
Require-Command "npm"
Require-Command "php"
Require-Command "ssh"
Require-Command "scp"
Require-Command "tar"

$ProjectPath = [System.IO.Path]::GetFullPath($ProjectPath)
Set-Location -LiteralPath $ProjectPath
[Environment]::CurrentDirectory = $ProjectPath

if (-not (Test-Path -LiteralPath (Join-Path $ProjectPath "artisan"))) {
    throw "Bukan root project Laravel: $ProjectPath"
}
if (-not (Test-Path -LiteralPath (Join-Path $ProjectPath "scripts\deploy\verify-vite-build.php"))) {
    throw "Deployment automation belum terpasang. Jalankan APPLY_DEPLOY_AUTOMATION.ps1 terlebih dahulu."
}

$currentBranch = (& git branch --show-current).Trim()
if ($currentBranch -ne $Branch) {
    throw "Branch aktif '$currentBranch', tetapi deployment memakai '$Branch'."
}

# Refuse secrets/generated artifacts if they are tracked.
$tracked = @(& git ls-files)
$dangerousTracked = @($tracked | Where-Object {
    $_ -eq '.env' -or
    ($_ -like '.env.*' -and $_ -ne '.env.example') -or
    $_ -like 'public/build/*' -or
    $_ -like '_patch_backup/*' -or
    $_ -like 'Santovate-PUBLIC-BUILD-*.zip' -or
    $_ -like 'Santovate-PUBLIC-BUILD-*.tar.gz'
})
if ($dangerousTracked.Count -gt 0) {
    Write-Host "File generated/secret masih tracked Git:" -ForegroundColor Red
    $dangerousTracked | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
    throw "Jalankan CLEAN_GIT_TRACKING.ps1 sekali sebelum deployment otomatis."
}

Run-Native "1/7 Frontend dependency" {
    if ($UseNpmInstall) { npm install --no-audit }
    else { npm ci --no-audit }
}

Run-Native "2/7 Build frontend" { npm run build }
Run-Native "3/7 Verify Vite build" { php scripts/deploy/verify-vite-build.php public/build }

$dirty = @(& git status --porcelain)
if ($dirty.Count -gt 0) {
    Write-Host "`nPerubahan source yang akan diproses:" -ForegroundColor Yellow
    $dirty | ForEach-Object { Write-Host $_ }

    if (-not $AutoCommit) {
        throw "Working tree belum clean. Commit/push source terlebih dahulu atau gunakan AutoCommit."
    }

    if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
        $CommitMessage = "Deploy Santovate " + (Get-Date -Format "yyyy-MM-dd HH:mm")
    }

    Run-Native "4/7 Stage source" { git add -A }

    $staged = @(& git diff --cached --name-only)
    $badStage = @($staged | Where-Object {
        $_ -eq '.env' -or
        ($_ -like '.env.*' -and $_ -ne '.env.example') -or
        $_ -like 'public/build/*' -or
        $_ -like '_patch_backup/*' -or
        $_ -like 'Santovate-PUBLIC-BUILD-*'
    })
    if ($badStage.Count -gt 0) {
        git restore --staged . | Out-Null
        Write-Host "File tidak boleh masuk commit:" -ForegroundColor Red
        $badStage | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
        throw "Commit dibatalkan. Rapikan .gitignore/tracking terlebih dahulu."
    }

    if ($staged.Count -gt 0) {
        Run-Native "5/7 Commit source" { git commit -m $CommitMessage }
    }
} else {
    Write-Host "`nWorking tree clean." -ForegroundColor Green
}

Run-Native "6/7 Push source" { git push origin $Branch }

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$artifact = Join-Path $env:TEMP "santovate-build-$stamp.tar.gz"
if (Test-Path -LiteralPath $artifact) { Remove-Item -LiteralPath $artifact -Force }

Run-Native "Package verified build" {
    tar -C (Join-Path $ProjectPath "public\build") -czf $artifact .
}

$defaultKey = Join-Path $env:USERPROFILE ".ssh\santovate_deploy_ed25519"
if ([string]::IsNullOrWhiteSpace($IdentityFile) -and (Test-Path -LiteralPath $defaultKey)) {
    $IdentityFile = $defaultKey
}

$scpArgs = @('-P', "$SshPort")
$sshArgs = @('-p', "$SshPort")
if (-not [string]::IsNullOrWhiteSpace($IdentityFile)) {
    $scpArgs += @('-i', $IdentityFile)
    $sshArgs += @('-i', $IdentityFile)
}

$remoteArtifact = "/home/$SshUser/santovate-build-$stamp.tar.gz"
$remoteTarget = "$SshUser@$SshHost`:$remoteArtifact"

Write-Host "`n[7/7 Upload verified build]" -ForegroundColor Cyan
& scp @scpArgs $artifact $remoteTarget
if ($LASTEXITCODE -ne 0) { throw "SCP gagal (exit code $LASTEXITCODE)." }

$bootstrapDir = "storage/app/deploy/bootstrap-current-build"
$remoteCommand = @(
    'cd ' + (Shell-Quote $RemoteProjectPath),
    'mkdir -p storage/app/deploy',
    'rm -rf ' + (Shell-Quote $bootstrapDir),
    'if [ -d public/build ]; then cp -a public/build ' + (Shell-Quote $bootstrapDir) + '; fi',
    'git fetch origin ' + (Shell-Quote $Branch),
    'git pull --ff-only origin ' + (Shell-Quote $Branch),
    'if [ ! -d public/build ] && [ -d ' + (Shell-Quote $bootstrapDir) + ' ]; then cp -a ' + (Shell-Quote $bootstrapDir) + ' public/build; fi',
    'SANTOVATE_COMPOSER_BIN=' + (Shell-Quote $ComposerBinary) + ' bash scripts/deploy/deploy-production.sh ' +
        (Shell-Quote $remoteArtifact) + ' ' +
        (Shell-Quote $Branch) + ' ' +
        (Shell-Quote $PhpBinary) + ' ' +
        (Shell-Quote $BaseUrl),
    'rm -rf ' + (Shell-Quote $bootstrapDir)
) -join ' && '

Write-Host "`n[REMOTE DEPLOY]" -ForegroundColor Cyan
& ssh @sshArgs "$SshUser@$SshHost" $remoteCommand
if ($LASTEXITCODE -ne 0) { throw "Remote deployment gagal (exit code $LASTEXITCODE)." }

Remove-Item -LiteralPath $artifact -Force -ErrorAction SilentlyContinue

Write-Host "`n======================================================" -ForegroundColor DarkGreen
Write-Host "SANTOVATE DEPLOYMENT BERHASIL" -ForegroundColor Green
Write-Host "======================================================" -ForegroundColor DarkGreen
Write-Host "Source  : origin/$Branch" -ForegroundColor White
Write-Host "Website : $BaseUrl" -ForegroundColor White
Write-Host "Build   : verified + atomic swap" -ForegroundColor White
