# One-time setup on the local Windows PC. Run in PowerShell (as the normal user, not admin):
#   powershell -ExecutionPolicy Bypass -File D:\laragon\www\erm.balkony-vikna.kyiv.ua\scripts\local-sync\install-task.ps1
# 1) creates the task "ERM sync-legacy (local)" that runs run-sync.ps1 every 5 minutes;
# 2) removes the two retired DB-copy tasks and moves their scripts to _retired (nothing is deleted).
# Docs: docs/arch/sync-legacy.md ("Single sync method").
$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-sync.ps1'
$taskName = 'ERM sync-legacy (local)'

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $runner + '"')
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "Task created: $taskName (every 5 min)"

foreach ($old in @('ERM live DB copy', 'ERM old CRM DB copy')) {
    if (Get-ScheduledTask -TaskName $old -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $old -Confirm:$false
        Write-Host "Old task removed: $old"
    }
}

$dir = 'D:\laragon\www\_archive\old-db-dumps\auto-sync'
$retired = Join-Path $dir '_retired'
New-Item -ItemType Directory -Force -Path $retired | Out-Null
foreach ($f in @('sync-live-db.ps1', 'sync-old-crm-db.ps1', 'setup-auto-sync.ps1', 'sync.log', 'sync-old-crm.log')) {
    $p = Join-Path $dir $f
    if (Test-Path $p) { Move-Item $p $retired -Force; Write-Host "Moved to _retired: $f" }
}

Start-ScheduledTask -TaskName $taskName
Write-Host 'First run started. Check the Sync page in the ERM (heartbeat) or storage\logs\local-sync.log.'
