$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Net.Http
$handler = [System.Net.Http.HttpClientHandler]::new()
$handler.UseCookies = $false
$client = [System.Net.Http.HttpClient]::new($handler)
$base = [Uri]'http://127.0.0.1:8000'
function New-DcmsRequest([string]$Method, [string]$Path, [string]$Cookie = '', [string]$Xsrf = '') {
    $request = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::new($Method), [Uri]::new($base, $Path))
    $request.Headers.Host = 'local.test'
    $request.Headers.Add('X-Forwarded-Proto', 'https')
    $request.Headers.Add('Accept-Encoding', 'gzip')
    $request.Headers.Add('Accept', 'application/json')
    if ($Cookie) { $request.Headers.TryAddWithoutValidation('Cookie', $Cookie) | Out-Null }
    if ($Xsrf) { $request.Headers.TryAddWithoutValidation('X-XSRF-TOKEN', $Xsrf) | Out-Null }
    return $request
}
try {
    $indexRequest = New-DcmsRequest 'GET' '/'
    $indexRequest.Headers.Remove('Accept-Encoding') | Out-Null
    $indexResponse = $client.SendAsync($indexRequest).GetAwaiter().GetResult()
    $index = $indexResponse.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if (-not $indexResponse.IsSuccessStatusCode -or $index -notmatch '/assets/index-[^" ]+\.js') { throw 'Production frontend did not serve from the backend.' }
    $assetPath = [regex]::Match($index, '/assets/index-[^" ]+\.js').Value
    $assetResponse = $client.SendAsync((New-DcmsRequest 'GET' $assetPath)).GetAwaiter().GetResult()
    $assetGzip = $assetResponse.Content.Headers.ContentEncoding.Contains('gzip')
    if (-not $assetResponse.IsSuccessStatusCode -or -not $assetGzip) { throw 'Production asset response was not gzip-compressed.' }

    $csrfResponse = $client.SendAsync((New-DcmsRequest 'GET' '/api/auth/csrf-cookie')).GetAwaiter().GetResult()
    $setCookies = $csrfResponse.Headers.GetValues('Set-Cookie')
    $xsrfCookie = ($setCookies | Where-Object { $_ -like 'XSRF-TOKEN=*' } | Select-Object -First 1).Split(';')[0]
    $sessionCookie = ($setCookies | Where-Object { $_ -like 'dcms_ngrok_session=*' } | Select-Object -First 1).Split(';')[0]
    if (($setCookies -join ';') -notmatch '(?i)secure' -or -not $xsrfCookie -or -not $sessionCookie) { throw 'HTTPS-forwarded CSRF/session cookies were not secure or were missing.' }
    $xsrfValue = [Uri]::UnescapeDataString(($xsrfCookie -split '=', 2)[1])
    $cookieHeader = $xsrfCookie + '; ' + $sessionCookie
    $secretPath = Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) '.local-private/dcms-demo-logins.txt'
    $loginLine = Get-Content -LiteralPath $secretPath | Where-Object { $_ -like 'process.owner@example.test  *' } | Select-Object -First 1
    if (-not $loginLine) { throw 'Fake Process Owner login is not prepared.' }
    $password = $loginLine.Substring('process.owner@example.test  '.Length)
    $login = New-DcmsRequest 'POST' '/api/auth/login' $cookieHeader $xsrfValue
    $login.Content = [System.Net.Http.StringContent]::new((ConvertTo-Json @{ email = 'process.owner@example.test'; password = $password }), [Text.Encoding]::UTF8, 'application/json')
    $loginResponse = $client.SendAsync($login).GetAwaiter().GetResult()
    if (-not $loginResponse.IsSuccessStatusCode) { throw "Login flow returned HTTP $([int]$loginResponse.StatusCode)." }
    $loginBody = $loginResponse.Content.ReadAsStringAsync().GetAwaiter().GetResult() | ConvertFrom-Json
    if ($loginBody.user.email -ne 'process.owner@example.test') { throw 'Login did not return the expected fake account.' }
    Write-Host "PASS: backend frontend; gzip asset=$assetGzip; forwarded HTTPS secure session/CSRF cookies; fake Process Owner login over session/CSRF."
}
finally {
    $client.Dispose()
    $handler.Dispose()
}
