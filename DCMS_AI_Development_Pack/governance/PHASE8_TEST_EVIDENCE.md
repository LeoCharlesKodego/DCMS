# Phase 8 Verification Evidence

## Automated verification run

- **Date:** 2026-11-16 (Asia/Manila)
- **Environment:** Workspace; local PHP 8.5.12; PHPUnit; in-memory SQLite via `phpunit.xml`; frontend production build using the existing Node/npm installation.
- **Application database:** Not migrated. RefreshDatabase migrations ran only inside isolated in-memory test databases.

| Evidence ID | Check | Actual result | Status |
|---|---|---|---|
| AUTO-01 | Full backend PHPUnit suite | 22 tests, 145 assertions; 22 passed, 0 failed | Pass |
| AUTO-02 | Frontend `npm.cmd run build` | TypeScript build and Vite production bundle completed successfully | Pass |
| AUTO-03 | PHP syntax checks | `php -l` passed for all 102 PHP files under app, migrations, seeders, routes, and tests | Pass |
| AUTO-04 | `php artisan route:list --except-vendor` | 70 registered routes listed | Pass |
| AUTO-05 | `git diff --check` | No whitespace errors | Pass |
| MIG-01 | `OnpMigrationRehearsalTest` over supplied source DOCX files | 7 files staged in fake private storage; original filenames and SHA-256 hashes matched; all remained `pending_verification`; reconciliation count was 7 pending / 0 verified | Pass (isolated rehearsal) |
| SEC-01 | Phase 8 authorization review tests | Anonymous protected reads rejected; System Administrator without business permissions denied protected Phase 6/7 reads; unit-scoped registry omitted another unit's document; admin retained only self-scoped inbox/notifications | Pass |
| REC-01 | Disposition hold recheck | A legal hold placed after a pending request blocked KMT approval; request remained pending | Pass |

## Local Phase 8 rerun (2026-11-16)

- **Environment:** Workspace-local PHP 8.5.12; Composer 2.11.2; PHPUnit 12.5.43; in-memory SQLite for tests. SQLite was enabled using `.local-tools/php-8.5.12/php-test.ini`; no project MySQL/MariaDB database was used for this suite.
- **Command:** `php artisan test` from `backend`, with `PHPRC` pointing to the workspace-local test PHP configuration.
- **Final result:** **22 tests, 145 assertions; 22 passed, 0 failures, 0 errors, 0 skipped.**
- **Initial environment attempts:** System PHP 8.2.12 could not boot because installed dependencies require PHP 8.4.1 or newer. PHP 8.5.12 initially ran but its child PHPUnit process lacked `pdo_sqlite`, resulting in **20 errors / 2 passes** and no meaningful feature-test execution. After pointing `PHPRC` to the workspace-local configuration with SQLite extensions enabled, the full suite passed as above. The `--no-interaction` option was also rejected by PHPUnit 12 and was omitted on rerun.
- **Frontend build:** `npm.cmd run build` passed (TypeScript and Vite production build).
- **Frontend lint:** `npm.cmd run lint` exited 0 with 10 warnings (React state updates inside effects and one missing `refreshBatches` effect dependency); no lint errors.
- **Localhost smoke:** Vite UI returned HTTP 200 at `http://127.0.0.1:5173`; API `/api/health` returned `{"status":"ok"}`; login succeeded for all six fictional accounts (Process Owner, KMT, Division Head, MCC, RMIC, QMR).
- **Sample file check:** PHP Fileinfo identified the synthetic `.pdf` as `application/pdf` and the synthetic `.docx` as `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, matching the upload validator's accepted types.
- **Local interactive demo:** All 16 migrations were applied only to `backend/database/local-phase8-demo.sqlite`; the migration-created institution was replaced in this disposable database with `Sample Training Facility`. `LocalPhase8DemoSeeder` added fictional users, scope, code components, a clearly labeled local procedure workflow, and fictional roles/permissions. Fake upload samples are in `backend/local-demo/samples/`; future uploaded demo files are stored under `backend/storage/app/private`. No patient data or supplied ONP source files were used. D-007/D-013 remain open; this local demo does not configure a backup job or production target.

## Owner acceptance

On 2026-11-16, the Project Owner accepted the Phase 8 evidence recorded at that point and authorized continued non-production work. This accepts the automated evidence only; it is not UAT sign-off, server/backup approval, production migration authorization, or go-live approval.

## Limits and not-run checks

- The rehearsal exercised source-file intake and hash/reconciliation behavior only. It did not map metadata, resolve duplicate/authority issues, or register live documents.
- No production or project database migrations were run.
- The separate disposable localhost demo database is migrated, but the pre-existing project database is untouched.
- UAT, all manual role/scope cases, confidentiality review by ONP, accessibility/usability sessions, MySQL concurrency/performance, and production TLS/network testing have not been performed.
- No backup job, destination, schedule, RPO/RTO, backup integrity run, or restore test exists yet; D-013 is open and deferred to deployment planning.
- A reviewable but unapproved D-007/D-013 candidate deployment and backup proposal is now in `PHASE8_DEPLOYMENT_PROPOSAL.md`. Its proposed values are not institutional decisions and have not been configured or exercised.
- D-007 server OS/hardware is unapproved. D-011 retention trigger and paper/digital scope are unresolved; the proposal remains inactive. D-022 paper-copy controls are unresolved.
- No live deployment, live data migration, training delivery, or go-live was performed.

## Next evidence required

Use `LOCALHOST_PHASE8_TEST_GUIDE.md` for the fictional localhost manual run and record actual tester/environment/results. ONP UAT, institutional approval, production database behavior, D-007/D-013, and production backup/restore evidence remain untested and open.
