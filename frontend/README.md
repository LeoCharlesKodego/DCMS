# DCMS Frontend

React + TypeScript + Vite foundation for the Ospital ng Palawan Document Control Management System (ONP DCMS).

## Local development

```powershell
npm install
npm run dev
```

The current interface is a responsive foundation preview. It has no API connection, authentication, database, or live document data yet. Module navigation and setup links show planned sections; they do not create or change records.

## Project constraints

- Core workflows must remain usable on the institution's local network without an internet dependency.
- Do not put ONP codes, approval chains, retention periods, or facility data in frontend constants.
- The backend must enforce authorization and workflow rules; hiding a control in this interface is not an access control.
- Document upload/migration must not imply approval, and release must remain distinct from approval.
- Do not display patient charts or patient-identifiable clinical records as ordinary DCMS records.

## Current implementation

- Responsive navigation shell for the documented DCMS modules.
- Overview empty state that does not invent institution or document data.
- Setup links for ONP, access, and configurable workflow areas.
- Native system font fallbacks; the interface makes no external font request.

Laravel API, persistence, authentication, and module workflows have not been implemented.
