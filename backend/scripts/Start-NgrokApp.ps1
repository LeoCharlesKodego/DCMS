$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Import-NgrokEnvironment.ps1')
Import-DcmsNgrokEnvironment
$backendRoot = Split-Path -Parent $PSScriptRoot
$repoRoot = Split-Path -Parent $backendRoot
$php = Join-Path $repoRoot '.local-tools/php-8.5.12/php.exe'
$ini = Join-Path $repoRoot '.local-tools/php-8.5.12/php-test.ini'
$expectedDb = [System.IO.Path]::GetFullPath((Join-Path $backendRoot 'local-demo/ngrok/database.sqlite'))
$actualDb = [System.IO.Path]::GetFullPath([string]$env:DB_DATABASE)
$policyFile = Join-Path $backendRoot '.local-private/ngrok-traffic-policy.yml'
if ($env:APP_DEBUG -ne 'false' -or $env:DB_CONNECTION -ne 'sqlite' -or $actualDb -ne $expectedDb) { throw 'Shared mode must use APP_DEBUG=false and the isolated SQLite demo database.' }
if ($env:APP_URL -match 'replace-with-assigned' -or $env:APP_ALLOWED_HOST_PATTERN -match 'replace-with-assigned') { throw 'Set the assigned ngrok dev domain first with Set-NgrokDomain.ps1.' }
if (-not (Test-Path -LiteralPath $policyFile)) { throw 'The gitignored Basic Auth policy file is missing.' }
$policy = Get-Content -LiteralPath $policyFile -Raw
if ($policy -match 'PLACEHOLDER_USER|PLACEHOLDER_PASSWORD') { throw 'Refusing to start: replace the placeholder Basic Auth credentials in .local-private/ngrok-traffic-policy.yml first.' }
if (-not (Test-Path -LiteralPath (Join-Path $backendRoot 'local-demo/ngrok/PASSWORDS_CHANGED'))) { throw 'Run Change-NgrokDemoPasswords.ps1 before starting shared mode.' }
if (-not (Test-Path -LiteralPath (Join-Path $repoRoot 'frontend/dist/index.html'))) { throw 'Production frontend build is missing; run npm run build in frontend.' }
if (-not (Test-Path -LiteralPath $php) -or -not (Test-Path -LiteralPath $ini)) { throw 'Bundled local PHP runtime is missing.' }
$storageRoot = Join-Path $backendRoot 'local-demo/ngrok-storage'
foreach ($directory in @('framework/cache/data', 'framework/sessions', 'framework/views', 'logs')) {
    New-Item -ItemType Directory -Path (Join-Path $storageRoot $directory) -Force | Out-Null
}
$env:PHPRC = $ini
Set-Location -LiteralPath $backendRoot
Write-Host "Serving production frontend and API at $($env:APP_URL) through loopback 127.0.0.1:8000. Per-file upload limit: 2 MiB. Press Ctrl+C to stop."
# variables_order=EGPCS populates $_ENV so artisan serve --no-reload forwards the shared-mode variables to its child server.
& $php -d variables_order=EGPCS artisan serve --host=127.0.0.1 --port=8000 --no-reload
