$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Import-NgrokEnvironment.ps1')
Import-DcmsNgrokEnvironment
$backendRoot = Split-Path -Parent $PSScriptRoot
$repoRoot = Split-Path -Parent $backendRoot
$php = Join-Path $repoRoot '.local-tools/php-8.5.12/php.exe'
$ini = Join-Path $repoRoot '.local-tools/php-8.5.12/php-test.ini'
$db = Join-Path $backendRoot 'local-demo/ngrok/database.sqlite'
$expectedDb = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/ngrok/database.sqlite'))
$actualDb = [System.IO.Path]::GetFullPath($db)
$backupDb = Join-Path $backendRoot 'local-demo/backups/ngrok-pre-share-2026-11-16/local-phase8-demo.sqlite'
$configuredDb = [System.IO.Path]::GetFullPath([string]$env:DB_DATABASE)
if ($actualDb -ne $expectedDb -or $configuredDb -ne $expectedDb -or $env:DB_CONNECTION -ne 'sqlite') { throw 'Refusing: shared mode must target only the isolated fake SQLite database.' }
if (-not (Test-Path -LiteralPath $backupDb)) { throw 'Pre-share SQLite backup is missing; stopping before migrations.' }
if (-not (Test-Path -LiteralPath $db)) { throw 'Isolated shared database clone is missing.' }
if (-not (Test-Path -LiteralPath $php) -or -not (Test-Path -LiteralPath $ini)) { throw 'Bundled local PHP runtime is missing.' }
New-Item -ItemType Directory -Path (Join-Path $backendRoot 'local-demo/ngrok-storage/cache') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $backendRoot 'local-demo/ngrok-storage/sessions') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $backendRoot 'local-demo/ngrok-private') -Force | Out-Null
$env:PHPRC = $ini
Set-Location -LiteralPath $backendRoot
& $php artisan migrate:fresh --force
if ($LASTEXITCODE -ne 0) { throw 'Migration failed; only the isolated sharing database was targeted.' }
& $php artisan db:seed --class=LocalPhase8DemoSeeder --force
if ($LASTEXITCODE -ne 0) { throw 'Fake demo seeding failed.' }
Write-Host 'Prepared the isolated ngrok demo SQLite database. The normal project database was not used.'
