# Temporary ngrok sharing (fake data only)

This setup uses one HTTPS ngrok HTTP endpoint forwarding only to `127.0.0.1:8000`. It serves the production frontend and Laravel API on the same origin. The app uses a fresh, isolated SQLite clone at `backend/local-demo/ngrok/database.sqlite`; it does not read the normal `.env`, MySQL, or any production database. The original fake SQLite file and private folder were backed up to `backend/local-demo/backups/ngrok-pre-share-2026-11-16` before preparation.

## Shared-mode protections

- `backend/.env.ngrok` is gitignored. It holds the temporary app URL, exact host allowlist, loopback trusted proxies, debug-off setting, secure host-only session cookie, same-site `Lax`, separate storage/session directories, isolated SQLite path, and a 2,048 KiB per-file application limit.
- The production frontend is built with the `ngrok` Vite mode and served by Laravel from `frontend/dist`. Text, JavaScript, CSS, HTML, and JSON responses are gzip-compressed when the client accepts gzip. Downloads keep their original file bytes.
- The shared PHP process uses `upload_max_filesize=2M` and `post_max_size=3M`; send one file per request, at most 2 MiB. Normal local uploads retain the 50 MiB limit.
- The ngrok Basic Auth Traffic Policy is one rule protecting the entire endpoint. Its credential file belongs at the gitignored `.local-private/ngrok-traffic-policy.yml`; never put credentials in tracked files. It is not ready until the owner supplies the Basic Auth username and password.
- The ngrok agent must target only `127.0.0.1:8000`. Never forward port 3306 or any database port.

## Local request rate

There is no timer-based API polling in the frontend. An idle authenticated tab makes **0 recurring API requests per minute**. A fresh tab makes one auth/session check; login adds one CSRF-cookie GET and one login POST; after login the app fetches the dashboard and notifications once each. User actions create additional requests. The server does not poll in the background.

## Prepare the isolated demo

Run PowerShell from the repository root. `ExecutionPolicy Bypass` applies only to this process; it does not change the machine policy.

```powershell
npm.cmd run build -- --mode ngrok
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\backend\scripts\Prepare-NgrokDemo.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\backend\scripts\Change-NgrokDemoPasswords.ps1
```

The prepare script asserts that it is migrating only the named cloned SQLite file, then runs `migrate:fresh` and seeds fictional demo accounts. The password script gives every demo account a new random 32-character password and writes only the tester credentials to ignored `.local-private/dcms-demo-logins.txt`. Six tester logins are listed: Process Owner, KMT, Division Head, MCC, RMIC, and staff recipient. The System Administrator account is rotated but deliberately excluded from the tester list. Share those DCMS credentials only with the intended testers. No QMR test login is seeded for the new flow.

Find the assigned dev domain in the [ngrok dashboard Domains page](https://dashboard.ngrok.com/domains). Copy its hostname only (no scheme or path), then set the exact app URL and host allowlist in the ignored environment file:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\backend\scripts\Set-NgrokDomain.ps1 -Domain 'YOUR-ASSIGNED-DEV-DOMAIN'
```

The app server reads this domain from `.env.ngrok`, trusts forwarded HTTPS only from loopback, and issues secure session cookies. The browser uses relative `/api` requests, so login, CSRF, sessions, uploads, and downloads stay on the same HTTPS origin.

## Start and stop

The app and tunnel run in separate terminal windows. From the repository root.

**Window 1 — PHP app server (loopback only):**

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\backend\scripts\Start-NgrokApp.ps1
```

This serves the production frontend and API on `127.0.0.1:8000` only. Per-file upload limit is 2 MiB. Leave this window running.

**Window 2 — ngrok tunnel:**

First verify the CLI flags against `ngrok http --help`. The verified flags for this setup are `--url` (the assigned dev hostname, no scheme needed in the value because ngrok adds `https://`) and `--traffic-policy-file` (the gitignored Basic Auth policy). Do not use any `--domain` flag — ngrok HTTP has no such flag.

```powershell
ngrok http 127.0.0.1:8000 --url https://YOUR-ASSIGNED-DEV-DOMAIN.ngrok.dev --traffic-policy-file .local-private/ngrok-traffic-policy.yml
```

Only forward `127.0.0.1:8000`. Never forward port 3306 or any database port.

**Stop:** Ctrl+C in the ngrok window first, then Ctrl+C in the app window. Then restore the fake data from the repository root:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\backend\scripts\Restore-NgrokDemo.ps1 -TunnelStopped
```

The restore script checks the saved SQLite SHA-256 before restoring and refuses to run until the operator confirms the tunnel is stopped. It also removes the gitignored `.local-private/dcms-demo-logins.txt` and `.local-private/ngrok-traffic-policy.yml` files if present; the dated pre-share backup stays for audit/recovery.

## Tester instructions

Test scope, role matrix, cases, and evidence rules are in `NGROK_DEMO_TEST_SCOPE.md`.

1. Open the single HTTPS dev URL and click through ngrok's browser warning page on the first visit.
2. Enter the owner-provided ngrok Basic Auth credentials, then the DCMS account for the needed role from `.local-private/dcms-demo-logins.txt`.
3. Upload one fictional PDF, DOCX, XLSX, JPEG, or PNG at a time, at most 2 MiB. Normal localhost upload limits remain 50 MiB.
4. Do not enter real hospital, patient, employee, or other actual ONP data.

## Free-plan usage

The account's stated limits are 1 GB/month transferred (uploads and downloads both count), 20,000 requests/month, one assigned dev domain, and at most five Traffic Policy rules per policy. This setup uses one endpoint and one Basic Auth rule. Check the ngrok dashboard Usage page during and after the session for request count and transferred bytes; stop sharing if usage approaches the account limits.
