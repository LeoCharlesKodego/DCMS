# DCMS Frontend

Web client for the Ospital ng Palawan Document Control Management System (ONP DCMS).

## Local development

```powershell
npm install
npm run dev
```

The interface uses Laravel session authentication for sign-in, sign-out, and required password changes. Every navigation entry calls its Laravel API and opens its own view; there is no placeholder module.

## Project constraints

- Core workflows must remain usable on the institution's local network without an internet dependency.
- Do not put ONP codes, approval chains, retention periods, or facility data in frontend constants.
- The backend must enforce authorization and workflow rules; hiding a control in this interface is not an access control.
- Document upload/migration must not imply approval, and release must remain distinct from approval.
- Do not display patient charts or patient-identifiable clinical records as ordinary DCMS records.

## Current implementation

- Responsive navigation shell for the documented DCMS modules.
- System-administrator page for configuring document type labels and hierarchical categories; code values are stored as entered while D-019 remains open.
- Overview empty state that does not invent institution or document data.
- Setup links for ONP, access, and configurable workflow areas.
- Self-hosted Archivo and Source Serif 4; the interface makes no external font request.
- Vite forwards local `/api` requests to the Laravel development server at `127.0.0.1:8000`.

The Laravel API exposes `GET /api/health` and the account routes described in
[`backend/README_AUTHENTICATION.md`](../backend/README_AUTHENTICATION.md). Workflow
templates are configurable; workflow execution and task assignment remain
deferred.

## Local development and tests

PHP 8.4+ is required. When the ignored `.local-tools/php-8.5.12` runtime is
present, the script uses it automatically.

```bash
bash backend/scripts/dev-local.sh
```

The script creates `backend/database/local-dev.sqlite`, applies migrations, seeds
ONP roles, creates the disposable local account `admin@dcms.local`, writes its
generated password to `backend/.env.dev-local`, and serves `/api` on
`http://127.0.0.1:8000`. The account command refuses to run in production or
against a non-SQLite database.

Start Vite in a second terminal:

```bash
cd frontend && npm run dev
```

Headless Chromium smoke tests (Playwright starts both servers):

```bash
cd frontend && npm run test:e2e
```

The suite runs on Chromium, Firefox, and WebKit. It signs in, checks the shell,
measures glass text contrast against rendered pixels, exercises the mobile drawer
(Escape, backdrop, focus return), validates the auth form and rate limiter, checks the
notification popover and its no-Popover fallback, and covers the overview summary strip
and the registry table (keyboard `Details` row, sticky header, 1280 and 1024 widths).
Screenshots and contrast results are written to `frontend/e2e/screenshots/` (ignored).

Re-seed sample notifications without touching the rest of the database:

```bash
bash backend/scripts/dev-local.sh --notifications-only
```

Vite forwards `/api` requests to Laravel. Local runtimes and MySQL are kept under
the ignored `.local-tools/` directory; the production server decision remains open.
