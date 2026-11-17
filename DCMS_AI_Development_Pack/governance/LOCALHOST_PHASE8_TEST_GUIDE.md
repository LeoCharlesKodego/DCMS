# Localhost Phase 8 and Phase 4B Test Guide

**Local, fictional data only.** Use the disposable SQLite file `backend/database/local-phase8-demo.sqlite`, the fake sample files below, and loopback addresses. Never point these commands at an ONP or production database. The six supplied ONP DOCX files are extraction test fixtures only; do not use them as demo submissions or seed records.

## Prepare the disposable demo database

Run from the repository root in PowerShell. This initializes only the named local demo SQLite file and seeds fictional accounts and a test workflow. Confirm the database path before running the migration command.

```powershell
Set-Location -LiteralPath 'C:\Users\Admin\Documents\DOCUMENT CONTROL MANAGEMENT SYSTEM\backend'
$env:PHPRC = (Resolve-Path '..\.local-tools\php-8.5.12\php-test.ini').Path
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = (Join-Path (Get-Location).Path 'database\local-phase8-demo.sqlite')
$env:DB_URL = ''
$env:APP_ENV = 'local'
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'sync'
if (-not (Test-Path $env:DB_DATABASE)) { New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null }
..\.local-tools\php-8.5.12\php.exe artisan migrate --force
..\.local-tools\php-8.5.12\php.exe artisan db:seed --class=LocalPhase8DemoSeeder --force
```

Do not run `migrate:fresh` against any other database. The code and test suite do not require applying migrations to the project database.

## Start and open the app

In two PowerShell windows:

**API** (run from `backend`):

```powershell
$env:PHPRC = (Resolve-Path '..\.local-tools\php-8.5.12\php-test.ini').Path
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = (Join-Path (Get-Location).Path 'database\local-phase8-demo.sqlite')
$env:DB_URL = ''
$env:APP_ENV = 'local'
$env:APP_URL = 'http://127.0.0.1:8000'
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'sync'
..\.local-tools\php-8.5.12\php.exe artisan serve --host=127.0.0.1 --port=8000
```

**UI** (run from `frontend`):

```powershell
npm.cmd run dev -- --host 127.0.0.1 --port 5173 --strictPort
```

Open **http://127.0.0.1:5173**. The API binds to `127.0.0.1:8000`; stop both with Ctrl+C.

## Fake demo accounts

All passwords are `LocalDemo!2026`.

| Role | Login |
|---|---|
| Process Owner | `process.owner@example.test` |
| KMT | `kmt@example.test` |
| Division Head | `division.head@example.test` |
| MCC | `mcc@example.test` |
| RMIC | `rmic@example.test` |
| System Administrator | `system.admin@example.test` |
| Staff recipient | `staff.recipient@example.test` |

The admin has no document-control role. The staff recipient has no approval, registry, or administrative role; they can see only distributions issued directly to them. RMIC has no records permission under the current decision.

Sample files contain fictional training labels only:

- `backend/local-demo/samples/SAMPLE-TRAINING-ONLY.pdf`
- `backend/local-demo/samples/SAMPLE-TRAINING-ONLY.docx`

## Manual test script

Use fictional titles and descriptions, for example `LOCAL DEMO — Visitor Access Procedure`. Do not enter patient, employee, or actual ONP data.

### 1. Per-role dashboard and access checks

1. Sign in as each account. Verify the dashboard combines the widgets for that account's active roles and labels its authorized unit scope.
2. Process Owner sees own-unit drafts, returned documents, and assigned tasks; menus include document registry and tasks.
3. KMT sees code assignment, submission verification, release, distribution acknowledgments/exemptions, and records tasks; menus include registry, tasks, distribution, records, migration, and reports as permitted.
4. Division Head sees only assigned owning-unit reviews and that unit's registry; no code assignment or release actions.
5. MCC sees approval tasks; no KMT release or registration actions.
6. RMIC sees no records authority or records menu unless a separate permission is deliberately granted in a future approved decision.
7. System Administrator sees account/configuration pages only. Directly request `/api/documents` and a workflow decision; both must be refused without document permissions.
8. Staff recipient sees only their own distributed inbox and acknowledgment status. Try another recipient's acknowledgment URL; the server must refuse it.
9. For all roles, try a registry document from another unit and an unassigned workflow-task URL/API action. The server must refuse access even if the URL is entered directly.

### 2. Submission checks and KMT verification

1. As Process Owner, create a Sample Procedure in Sample Operations Unit. Save the provided fake PDF or DOCX as draft. Confirm unsupported extensions, files over 50 MiB, unreadable files, and encrypted files are rejected or recorded as hard-blocked; a filename change alone does not make a file valid.
2. Upload the same sample file twice across draft versions. Confirm SHA-256 duplicate warnings appear and neither copy is overwritten.
3. As KMT, assign an official test code using the seeded fictional code components. Only KMT may assign it. Confirm the file's extracted code is compared with the assignment; no code before assignment is shown as `awaiting KMT code assignment`.
4. A deliberately wrong or missing code must prevent workflow submission. As Process Owner, use **Replace current files and rerun checks** to correct the same draft to match KMT's assigned code; confirm a new run number, superseded source history, and prior findings are retained.
5. Use a scanned PDF or JPEG/PNG test image: confirm no OCR, KMT manual code entry is required, and the code source is recorded as manual. Confirm title formatting says `not checked, KMT to verify manually` for formats without a reliable title rule.
6. As Process Owner, submit only after code checks pass. As KMT, open the verification task. Resolve a duplicate or manual formatting finding with a reason and select **verified**. A hard block cannot be waived. Select **Return for revision** with a reason; a blank reason must fail.
7. Correct and resubmit the returned draft; verify a new pass/run and retained prior decision history.

### 3. Approval, effective date, release, and acknowledgment

1. For a document type explicitly configured to `Phase 4B manual / SOP / guideline`, verify task order is Process Owner submit → KMT verification → Division Head of the owning unit review → MCC approval. There is no QMR task.
2. Attempt a Division Head decision from a different unit, a KMT MCC approval, and an MCC release. Each must be refused server-side.
3. After MCC approval, Process Owner proposes the effective date. KMT confirms that exact date and releases only on/after it. Approval and release remain separate audit events.
4. The same KMT account that verified must not release while the setting is OFF. A System Administrator may enable the logged setting; then the same KMT account can release with separate verification/release audit events. Confirm setting history records actor and timestamp.
5. KMT distributes the released exact version to the staff recipient with acknowledgment required. Staff acknowledges their own receipt. KMT may exempt only that recipient with a reason; confirm another recipient's status is unchanged.

### 4. Type routing and settings

1. As System Administrator, set each document type's approval-flow profile explicitly. Use Phase 4B only for manuals, procedures, and ordinary guidelines; keep policies and specific-guideline templates source-defined; leave forms/resolutions deferred while D-017 is open.
2. Attempt to configure a Phase 4B workflow with QMR, without KMT verification, or without the owning Division Head stage; publishing must fail.
3. As KMT, change the 14 pt title default and confirm a setting-history row records the value, actor, and time. For DOCX, only a title paired with the explicit `Document Title:` field in the same document-control table cell is evaluated; direct formatting and resolvable OOXML paragraph/character style inheritance are considered. PDF code extraction currently handles simple uncompressed literal text only; compressed streams/font maps require KMT manual verification. The six fixture findings/rules are in `specifications/SUBMISSION_VALIDATION.md`.

### 5. Manual backup and restore to a local folder

1. Stop the local API and UI with Ctrl+C. Copy only `backend/database/local-phase8-demo.sqlite` and `backend/storage/app/private` to a clearly named local folder under `backend/local-demo/backups`.
2. Record SHA-256 of the copied SQLite file with `Get-FileHash -Algorithm SHA256`.
3. To rehearse restore, stop the services and copy the backup over the same local demo SQLite file and private-storage folder. Restart locally and verify the test records/files. Do not use any production backup path or destination.
