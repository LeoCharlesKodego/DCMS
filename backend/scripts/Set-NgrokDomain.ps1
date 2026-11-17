param([Parameter(Mandatory = $true)][string]$Domain)
$backendRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $backendRoot '.env.ngrok'
if ($Domain -notmatch '^[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$') { throw 'Pass only the assigned hostname, without https:// or a path.' }
if (-not (Test-Path -LiteralPath $envFile)) { throw 'backend/.env.ngrok is missing.' }
$content = [System.IO.File]::ReadAllText($envFile, [System.Text.Encoding]::UTF8)
$pattern = '^' + [regex]::Escape($Domain) + '$'
$content = [regex]::Replace($content, '(?m)^APP_URL=.*$', [System.Text.RegularExpressions.MatchEvaluator]{ param($m) 'APP_URL=https://' + $Domain })
$content = [regex]::Replace($content, '(?m)^APP_ALLOWED_HOST_PATTERN=.*$', [System.Text.RegularExpressions.MatchEvaluator]{ param($m) 'APP_ALLOWED_HOST_PATTERN=' + $pattern })
[System.IO.File]::WriteAllText($envFile, $content, [System.Text.UTF8Encoding]::new($false))
Write-Host "Configured the shared app URL and host allowlist for $Domain."
