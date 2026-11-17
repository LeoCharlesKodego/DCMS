# Local authentication and account recovery

DCMS accounts are stored in the configured MySQL database. Passwords are hashed by Laravel; plaintext passwords are never written to source files or logs. Sessions use Laravel's file driver and session encryption. The browser uses the same-origin Vite `/api` proxy during development and Laravel's session cookie plus CSRF protection.

## First administrator

1. Copy `.env.example` to `.env`, set a private database password, and generate the application key with `php artisan key:generate`.
2. Create the `dcms` database and its least-privileged application user in MySQL. Keep MySQL bound to the local machine during development.
3. Run `php artisan migrate` from the `backend` directory after confirming the configured database connection.
4. Load the ONP role definitions and explicit source duties with `php artisan db:seed --class=OnpRolePermissionSeeder`. This uses only roles and duties recorded in `reference/ONP_ORIGINAL_GUIDELINES.md`; it does not configure unresolved approval sequencing or add roles from the generic suggested-role list.
5. Run `php artisan dcms:bootstrap-admin` in a private terminal. Enter the named administrator account and a password of at least 12 characters when prompted. The command is refused after any account already exists and records the bootstrap event.
6. Start Laravel and the frontend using the development commands in the frontend README, then sign in with that account.
7. Use **Users & access** to create individual accounts, assign an ONP source role to its work scope and validity dates, deactivate accounts, and reset passwords. New accounts must change their temporary password at first sign-in. System administrator status remains separate from document roles.
8. Use **Institution setup** to maintain the ONP profile and build the organizational hierarchy. Organizational unit names and levels are configurable. The original ONP unit-code lists have not been seeded while the source duplicates remain unresolved.

The bootstrap command asks for the password without echoing it. Do not place passwords in command arguments, source files, screenshots, or shared notes. Create a second individually named system administrator once the user-administration module is available.

## Normal account use

- Each person signs in with their own local account. Accounts marked inactive cannot sign in.
- A new session is generated at login and invalidated at logout. Login attempts are limited to five per email and IP address per minute.
- A password reset by a system administrator requires a new password and a reason. The person must change that temporary password at next sign-in. Passwords must be at least 12 characters.
- The reset API is `POST /api/admin/users/{user}/password` and is protected by the session, CSRF, and system-administrator checks. The signed-in administrator cannot reset their own password through this endpoint; they use the normal change-password flow.
- Password reset and bootstrap records are stored in `account_security_events`. Ordinary application accounts have no endpoint for changing or deleting these records.
- Password-reset email is not configured because the approved installation uses local accounts and must work offline. A user who forgets a password contacts a system administrator.

## Primary administrator recovery

Normally, the second individually named system administrator resets the primary administrator's password through the protected administration function. The primary administrator must choose a new password at the next sign-in.

If no active system administrator can sign in, two authorized people must verify each other's identity in person, confirm that the ordinary administrator reset path is unavailable, and work together at the DCMS server console. With the server application account, run:

```powershell
php artisan dcms:recover-admin admin@example.local
```

The command asks for both people's names, the reason, and a temporary password without echoing it. It requires two different names, shows the account being recovered, and asks for explicit confirmation. It updates only an existing active administrator, sets `must_change_password`, invalidates the remember token, and records both names, the target, and reason in `account_security_events`. The recovered administrator must immediately sign in and set a new private password. Do not put the temporary password in a command argument or recovery record.

If either verifier cannot affirm the authorization or the named target is not an active system administrator, stop and refer the issue to the institution's designated authority. The command records supplied names; the two people must independently verify identity and authority before proceeding.

## Deployment security

The local development setup uses HTTP on loopback. Before serving DCMS to other LAN computers, configure HTTPS on the production web server and set `SESSION_SECURE_COOKIE=true` in its protected environment. Keep `.env`, MySQL credentials, session files, and database backups inaccessible to web users. Current `/admin/*` and document-configuration write endpoints require System Administrator access; all other protected APIs require an authenticated active account. Role-permission checks use active assignments and validity dates; organizational-unit grants require an exact unit match and do not inherit to descendants. System Administrator status never bypasses document-role checks. The document registry uses those grants for scoped visibility and creation; KMT code assignment and Master Code Register access also require their source-derived permissions. D-017 approval sequencing remains unresolved. To initialize roles, run `php artisan db:seed --class=OnpRolePermissionSeeder` after migrations. Migrations and seeders have not been run.
# Document registry setup

After reviewing the generated migrations, apply them in the intended deployment, then seed the ONP role permissions and official code lists:

```bash
php artisan migrate
php artisan db:seed --class=OnpRolePermissionSeeder
php artisan db:seed --class=OnpCodeListSeeder
```

The ONP code-list seeder reads `DCMS_AI_Development_Pack/reference/ONP_CODE_LISTS.md` from the project root. It preserves every listed source row, marks repeated/flagged conflict codes unavailable, and does not invent replacements. Review the source file and current plan before running these commands. Migrations and seeders have not been run as part of Task 3.3 implementation.

## Versioned document files

Task 3.4 accepts PDF, DOCX, XLSX, JPEG, and PNG files up to 50 MiB each. Files are stored on Laravel's private local disk under generated names, are not exposed through the public storage link, and are served only through an authenticated scope-checked download endpoint as attachments. Each uploaded file has a SHA-256 hash. Inline previews and file overwrite/delete operations are not provided.

Configure the PHP web server with `upload_max_filesize=50M`. Set `post_max_size` high enough for the combined size of the files sent in one upload plus multipart overhead; it must exceed 50M even for a single maximum-size file. Migrations remain generated but unapplied; no upload or download is available until the database migration is reviewed and run. The migration refuses rollback after version or file metadata exists; back up and archive the private files and database before any rollback plan.

## Lifecycle and audit

Document creation, official code assignment, file/version creation, review decisions, approval, Process Owner effective-date proposals, KMT effective-date confirmations, release, and supersession are recorded in append-only audit events. The registry shows events only for documents within the viewer's assigned scope. Audit entries record authenticated account actions; they are not legal or digital signatures.

Lifecycle actions follow the explicit ONP source assignments: QMR review; Department Head then Service Chief review on policy/specific-guideline templates; MCC final approval for manual, procedure, and guideline code types. After approval, the Process Owner proposes the effective date and KMT confirms it; KMT releases an approved version on or after that date. The app blocks final approval when the source does not name an authority. D-017 remains open for other document types and the internal/external form distinction; the Forms Committee's pre-coding step is not automated. Workflow execution, delegation, withdrawal, and archive authority remain for later tasks.

Workflow template configuration is available to System Administrators under **Workflow configuration**. It stores versioned drafts and only offers source-role permissions for review, approval, and release stages. Publishing requires QMR review before final approval and KMT release afterward; quorum greater than one, optional stages, recommendations, and parallel stages remain blocked while D-017 is open. Publishing a template does not assign document approval authority to the System Administrator. This Task 4.2 migration is generated but has not been run; workflow instances and task execution are deferred to Task 4.3.
