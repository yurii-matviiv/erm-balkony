# Local (Windows) runner for the ONLY sync method: php artisan sync:legacy
# Called every 5 minutes by the Windows scheduled task "ERM sync-legacy (local)" (see install-task.ps1),
# or by hand:  powershell -ExecutionPolicy Bypass -File scripts\local-sync\run-sync.ps1 -Force   (sync right now, ignores the interval)
#
# Reads the LIVE old CRM DB (host balkonio.mysql.tools, user balkonio_ro, SELECT only) and writes only into the LOCAL DB.
# The live credentials are NOT stored in .env: they are decrypted from ro.pwd (Windows DPAPI, this user only) and passed to
# the PHP process as environment variables, which Laravel prefers over .env. So the project .env keeps LEGACY_DB_* local
# (127.0.0.1) and Claude's local-runner (artisan/tinker, refuses a non-local .env) keeps working.
# It runs ONLY sync:legacy, NOT the whole `schedule:run`: the local DB holds production data, and the rest of the schedule
# (conversions:run, reminders:dispatch, audience:run ...) would send real conversions to Google and real push from this PC.
# Docs: docs/arch/sync-legacy.md ("Single sync method").
param([switch]$Force)
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$php = Get-ChildItem 'D:\laragon\bin\php' -Directory | Where-Object { $_.Name -like 'php-8*' } |
       Sort-Object Name -Descending | Select-Object -First 1
$phpExe = Join-Path $php.FullName 'php.exe'
$log = Join-Path $project 'storage\logs\local-sync.log'
$sec = Get-Content 'D:\laragon\www\_archive\old-db-dumps\auto-sync\ro.pwd' | ConvertTo-SecureString
$env:LEGACY_DB_HOST = 'balkonio.mysql.tools'
$env:LEGACY_DB_PORT = '3306'
$env:LEGACY_DB_DATABASE = 'balkonio_erm'
$env:LEGACY_DB_USERNAME = 'balkonio_ro'
$env:LEGACY_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
Set-Location $project
try {
    if ($Force) { $out = & $phpExe artisan sync:legacy 2>&1 | Out-String } else { $out = & $phpExe artisan sync:legacy --scheduled 2>&1 | Out-String }
} finally {
    Remove-Item Env:\LEGACY_DB_PASSWORD -ErrorAction SilentlyContinue
}
if ($Force) { Write-Host $out }
# --scheduled exits silently when auto-sync is off or the interval has not passed: log only real runs and errors.
if ($out -match 'Done\.|FAILED|Exception|Error') {
    $summary = ($out -split "`r?`n" | Where-Object { $_ -match 'Done\.|FAILED|Exception|Error' }) -join ' | '
    Add-Content -Path $log -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' ' + $summary)
}
