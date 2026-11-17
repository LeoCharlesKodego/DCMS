$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Import-NgrokEnvironment.ps1')
Import-DcmsNgrokEnvironment
$backendRoot = Split-Path -Parent $PSScriptRoot
$repoRoot = Split-Path -Parent $backendRoot
$php = Join-Path $repoRoot '.local-tools/php-8.5.12/php.exe'
$env:PHPRC = Join-Path $repoRoot '.local-tools/php-8.5.12/php-test.ini'
Set-Location -LiteralPath $backendRoot
& $php artisan demo:change-ngrok-passwords
if ($LASTEXITCODE -ne 0) { throw 'Demo account password update failed.' }
