param([switch]$TunnelStopped)
$ErrorActionPreference = 'Stop'
if (-not $TunnelStopped) { throw 'Stop ngrok with Ctrl+C first, then pass -TunnelStopped to confirm no public tunnel remains.' }
$backendRoot = Split-Path -Parent $PSScriptRoot
$repoRoot = Split-Path -Parent $backendRoot
$expectedBackup = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/backups/ngrok-pre-share-2026-11-16'))
$backup = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/backups/ngrok-pre-share-2026-11-16'))
$sourceDb = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'database/local-phase8-demo.sqlite'))
$sourcePrivate = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'storage/app/private'))
$shareDb = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/ngrok'))
$sharePrivate = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/ngrok-private'))
$shareStorage = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/ngrok-storage'))
if ($backup -ne $expectedBackup -or -not (Test-Path -LiteralPath (Join-Path $backup 'local-phase8-demo.sqlite'))) { throw 'The expected pre-share backup is missing; nothing was removed.' }
$expectedHash = (Get-Content -LiteralPath (Join-Path $backup 'database.sha256') -Raw).Trim()
$actualHash = (Get-FileHash -LiteralPath (Join-Path $backup 'local-phase8-demo.sqlite') -Algorithm SHA256).Hash
if ($actualHash -ne $expectedHash) { throw 'The pre-share SQLite backup hash does not match; nothing was removed.' }
$listeners = Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue
if ($listeners) { throw 'Stop the local app with Ctrl+C before restoring the fake data.' }
Copy-Item -LiteralPath (Join-Path $backup 'local-phase8-demo.sqlite') -Destination $sourceDb -Force
New-Item -ItemType Directory -Path $sourcePrivate -Force | Out-Null
Get-ChildItem -LiteralPath (Join-Path $backup 'private') -Force | Copy-Item -Destination $sourcePrivate -Recurse -Force
$safeRoot = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo')) + [System.IO.Path]::DirectorySeparatorChar
foreach ($target in @($shareDb, $sharePrivate, $shareStorage)) {
    if ($target.StartsWith($safeRoot, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $target)) {
        Remove-Item -LiteralPath $target -Recurse -Force
    }
}
$secretRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot '.local-private')) + [System.IO.Path]::DirectorySeparatorChar
foreach ($secret in @((Join-Path $repoRoot '.local-private/dcms-demo-logins.txt'), (Join-Path $repoRoot '.local-private/ngrok-traffic-policy.yml'))) {
    $secretPath = [System.IO.Path]::GetFullPath($secret)
    if ($secretPath.StartsWith($secretRoot, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $secretPath)) {
        Remove-Item -LiteralPath $secretPath -Force
    }
}
Write-Host 'Restored the original local fake SQLite database and private documents folder; removed the isolated sharing data.'
