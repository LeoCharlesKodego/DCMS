# Technical Decisions and Open Technical Choices

## Provisional baseline

The following is a candidate stack, not a final approved decision:
- Backend: Laravel / PHP
- Frontend: React
- Styling: Tailwind CSS
- Database: MySQL or MariaDB
- Deployment: local institutional server, browser-based clients
- File storage: private server-side filesystem initially
- API: authenticated application API

This candidate reflects technologies used in the user's prior work, but the DCMS stack must be explicitly approved before implementation.

## Decisions requiring approval

1. Backend and frontend architecture: Laravel with React, or a Laravel-rendered application.
2. Database engine and supported version.
3. Single institution per installation versus multi-institution tenancy.
4. Server operating system and hardware.
5. Document file storage strategy and maximum file sizes.
6. Authentication method and password reset process on an offline LAN.
7. HTTPS certificate approach for local network access.
8. Whether electronic signatures are required and what legal/technical standard applies.
9. PDF/Office preview and conversion requirements.
10. Backup destination, schedule, retention, and recovery objectives.

## Technical constraints

- Do not add frameworks or dependencies without approval.
- Do not use external cloud services as a dependency for core workflows.
- Do not store uploaded files in a public web directory.
- Do not expose storage paths or direct unrestricted file URLs.
- Do not claim an electronic signature is legally compliant without legal and technical verification.
- Use versioned migrations and maintain a known-good backup before schema changes.
