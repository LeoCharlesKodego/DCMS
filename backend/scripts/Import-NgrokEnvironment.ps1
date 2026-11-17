function Import-DcmsNgrokEnvironment {
    $backendRoot = Split-Path -Parent $PSScriptRoot
    $envFile = Join-Path $backendRoot '.env.ngrok'
    if (-not (Test-Path -LiteralPath $envFile)) { throw 'backend/.env.ngrok is missing.' }
    foreach ($line in Get-Content -LiteralPath $envFile) {
        if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$') {
            $name = $Matches[1]
            $value = $Matches[2].Trim()
            if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            Set-Item -Path "Env:$name" -Value $value
        }
    }
    Set-Item -Path Env:APP_ENV -Value 'ngrok'
}
