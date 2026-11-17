# DCMS Frontend

React + TypeScript + Vite foundation for the Ospital ng Palawan Document Control Management System (ONP DCMS).

## Local development

```powershell
npm install
npm run dev
```

The current interface is a responsive foundation preview. It now uses Laravel session authentication for sign-in, sign-out, and required password changes. Document modules remain previews; their navigation does not create or change records.

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
- Native system font fallbacks; the interface makes no external font request.
- Vite forwards local `/api` requests to the Laravel development server at `127.0.0.1:8000`.

The Laravel API exposes `GET /api/health` and the account routes described in
[`backend/README_AUTHENTICATION.md`](../backend/README_AUTHENTICATION.md). Document
workflows, scoped role assignment, and graphical user administration are not
implemented yet.

For local development, start Laravel from the repository root:

```powershell
.\.local-tools\php-8.5.12\php.exe .\backend\artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal, start Vite:

```powershell
Set-Location frontend
npm.cmd run dev
```

Vite forwards `/api` requests to Laravel. Local runtimes and MySQL are kept under
the ignored `.local-tools/` directory; the production server decision remains open.
