# PLAN: DCMS

Rules:
- Work on the FIRST unchecked task only. Tick it when done. Then STOP.
- Phases 0 and 1 are DOCUMENTATION ONLY. No application code, no installs, no migrations.
- A phase marked LOCKED must not start until the previous phase's approval gate is ticked BY ME.
- Before starting a locked phase, break it into small tasks (one session each),
  list them here, and get my approval.
- Source of phase scope: `DCMS_AI_Development_Pack/governance/DEVELOPMENT_PROCESS.md`
- APPROVED ROADMAP (D-018): use the development pack's Phases 0-8 as the controlling sequence.
  Include all proposal Phase 0-10 deliverables by mapping them into the pack phases below.

## Approved roadmap mapping (D-018)

- Proposal Phase 0 (requirements and institutional assessment) maps to pack Phases 0-1.
- Proposal Phases 1-3 (foundation, institution/organization setup, user/access control) map to pack Phase 2.
- Proposal Phase 4 (document registry and files) maps to pack Phase 3. Audit trail work from Proposal Phase 9 also starts here as specified by the pack.
- Proposal Phase 5 (review and approval workflow) maps to pack Phase 4.
- Proposal Phase 6 (release, distribution, acknowledgment) maps to pack Phase 5.
- Proposal Phase 7 (existing-document migration) maps to pack Phase 6.
- Proposal Phase 8 (external references, records, retention) and the reports, notifications, and backup administration work from Proposal Phase 9 map to pack Phase 7.
- Proposal Phase 10 (pilot testing, institutional validation, training, deployment) maps to pack Phase 8.

All proposal deliverables are included; the pack phase sequence controls ordering and phase gates.

## Phase 0: Project intake (no code)
- [x] **0.1 Intake review**
  - Do: follow `DCMS_AI_Development_Pack/prompts/PROJECT_INTAKE_PROMPT.md`
  - Also read: `DCMS_AI_Development_Pack/specifications/PROPOSAL_BASELINE.md` (list its flagged differences in the review)
  - Also read: `DCMS_AI_Development_Pack/reference/ONP_ORIGINAL_GUIDELINES.md` and `reference/ONP_CODE_LISTS.md` (list the source inconsistencies from section 12 of the first file; do not resolve them)
  - Files: create `DCMS_AI_Development_Pack/governance/INTAKE_REVIEW.md` (nothing else)
  - Done when: file covers the 10 deliverables in the intake prompt, each statement
    labeled verified fact / assumption / recommendation / open decision
- [x] **0.2 Contradictions and gaps list**
  - Files: append to `DCMS_AI_Development_Pack/governance/INTAKE_REVIEW.md`
  - Done when: lists contradictions, missing requirements, and the top questions
    I must answer (max 5, no repeats of answered ones)
- [x] **0.3 GATE: I confirm the intake review** (user ticks this, not the AI)

## Phase 1: Architecture approval (no code)
- [x] **1.1 Record my decisions** (D-018 roadmap, D-001 incl. confirming ONP as first institution, D-003, D-005, D-006, D-007 first; then D-015 to D-017, then D-019 to D-023)
  - Files: `DCMS_AI_Development_Pack/governance/OPEN_DECISIONS.md`
  - Done when: each answered decision is recorded with the pack's decision template
    (question, options, approved decision, reason, impacted files, approved by, date, status)
  - Rule: the AI records ONLY what I state. It never fills in an answer.
- [x] **1.2 Domain model and ERD**
  - Files: `DCMS_AI_Development_Pack/architecture/DOMAIN_MODEL.md`
  - Use as input: the ONP DRRF, three registers, and header/revision-history/signature/distributed-copy blocks (`reference/ONP_ORIGINAL_GUIDELINES.md` sections 10 and 11)
  - Done when: entities, relationships, cardinalities, constraints are listed for my review
- [x] **1.3 API boundaries, security model, authority matrix**
  - Files: `DCMS_AI_Development_Pack/architecture/SYSTEM_ARCHITECTURE.md`,
    `DCMS_AI_Development_Pack/specifications/ROLES_AND_PERMISSIONS.md`
  - Done when: reviewed by me
- [x] **1.4 Workflow engine behavior**
  - Files: `DCMS_AI_Development_Pack/workflows/WORKFLOW_ENGINE.md`
  - Done when: reviewed by me
- [x] **1.5 GATE: "Phase 1 approved" (user ticks this, not the AI).**
  Coding stays forbidden until this is ticked and the stack (D-006) is recorded.

## Phase 2: Foundation (LOCKED until 1.5)
Scope: repo/environment setup, authentication and user management, institution and
organization configuration, role and permission enforcement, automated tests, seed data.
Seed data for the first institution comes from `reference/ONP_CODE_LISTS.md`, loaded as DATA only (after D-019 to D-021 are decided).
- [x] 2.0 Break into small tasks and get my approval
- [x] 2.1 Confirm the development environment and software version baseline. D-007 production server OS/hardware is deferred; its recommendation is a planning assumption only.
  - Verified local environment (2026-11-12): Windows; PHP 8.2.12 with `pdo_mysql`; Node 24.16.0; npm 11.13.0; Composer is not installed; XAMPP provides MariaDB 10.4.32, not the approved MySQL database.
  - Approved local development baseline (owner approval 2026-11-12): PHP 8.5; Laravel 13.x (requires PHP 8.3 or higher); MySQL 8.4 LTS; Composer 2.11.x; keep Node 24.16.0/npm 11.13.0.
  - The owner approved setting up these versions locally. No tools were installed in task 2.1; setup belongs to task 2.2.
  - References: [Laravel 13 server requirements](https://laravel.com/framework/docs/deployment), [PHP supported versions](https://www.php.net/supported-versions.php), [MySQL release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html), [Composer downloads](https://getcomposer.org/download/), [Vite 8 Node.js requirements](https://vite.dev/guide/).
- [x] 2.2 Prepare the application foundation: integrate the approved Laravel/PHP API stack with the existing React/TypeScript/Vite frontend and configure local-only settings. Preserve the current frontend behavior.
- [x] 2.3 Implement local authentication and account lifecycle: individual accounts, secure sessions/password storage, bootstrap administrator, and the approved administrator recovery process.
- [x] 2.4 Implement user and role administration: administrators manage user accounts and password resets; assign separate, scoped roles to users. Do not grant approval authority from the administrator role.
  - Added administrator-only account create/edit/deactivate, password reset, role-name management, and user role assignments with work-scope text and validity dates. New accounts require a password change at first sign-in. System administrator accounts remain separate and cannot receive document-role assignments through this screen.
  - No preset role or permission matrix was added. Permission enforcement remains Phase 2.6. The schema migration was generated but not run (2026-11-12).
- [x] 2.5 Implement institution and organizational structure configuration for the single ONP installation. Keep units configurable; do not load ONP code-list seed data until D-019 to D-021 are resolved.
  - Added the ONP profile (name, short name, facility type) from the original guideline, editable institution contact/timezone/status/logo fields, and admin-managed hierarchical organizational units with configurable names and levels. No official unit/code-list rows were loaded; duplicate handling remains open under D-021.
  - Role assignments can now reference a configured organizational unit in addition to their work-scope text and validity dates. Added migrations; none have been run (2026-11-12).
- [x] 2.6 Enforce role and scope authorization on every protected API operation, including account management and institution configuration.
  - Centralized System Administrator enforcement on every `/admin/*` API and document-configuration write; all API routes continue to require an authenticated active account except health, CSRF, and sign-in endpoints.
  - Added server-side role-permission middleware using active assignments and validity dates. Organizational-unit grants require an exact unit match; System Administrator status does not bypass document-role checks. Current Phase 2/3 APIs do not yet include document actions to attach these source-role grants to.
  - Seeded role names and only explicit duties from the ONP original guideline. No custom role/permission editor or approval sequence was added. D-017 remains open. Migrations and the ONP role-permission seeder have not been run (2026-11-12).
- [x] 2.7 Verify Phase 2 acceptance criteria with automated authentication and authorization tests; document setup and recovery procedures; obtain Phase 2 review before continuing.

## Phase 3: Document control core (LOCKED until Phase 2 approved)
Scope: document types/categories, registry and numbering, version and file storage,
document lifecycle, audit events.
- [x] 3.0 Break into small tasks and get my approval
  - Owner approved this breakdown (2026-11-12).
- [x] 3.1 Draft the physical data schema for document types/categories, document identity, versions/files, lifecycle events, and audit. Preserve unresolved code/revision decisions; present the draft for owner review. No migrations.
  - Files: create `DCMS_AI_Development_Pack/architecture/DATABASE_SCHEMA.md`.
  - Draft delivered for owner review (2026-11-12); D-019 and D-020 remain open. No migration generated or run.
- [x] 3.2 Implement configurable document types and hierarchical categories after the schema is approved.
  - Added administrator-managed type/category APIs and UI with a reversible migration. No migration run and no ONP code-list seed values loaded (2026-11-12).
- [x] 3.3 Implement the document registry and configurable numbering after D-019 and D-020 are resolved and the relevant schema is approved.
  - Added scoped register viewing and Process Owner document requests, KMT code assignment from official components, separate Master Code Register entries, and Major.Minor display labels. The source seeder preserves all official rows and blocks conflicted codes, including case-insensitive duplicates; no substitutes are created. Added migrations and setup notes; migrations and seeders have not been run (2026-11-12).
- [x] 3.4 Implement versioned file storage and integrity hashes after the schema is approved.
  - Repository review (2026-11-12) found no document-version/file migrations, storage service, upload/download API, or corresponding registry UI. The existing registry stored metadata only. The owner approved the Task 3.4 file baseline; the generated migration remains unapplied.
  - Owner approved D-010: PDF, DOCX, XLSX, JPEG, and PNG up to 50 MiB each, no inline preview, private storage, SHA-256 hashes, and scope-checked downloads. Added draft versions and immutable file records, SHA-256 verification on write/download, upload validation, and registry upload/history/download UI. Migration generated but not run (2026-11-12).
- [x] 3.5 Implement document lifecycle transitions and audit events after the schema is approved; preserve approval and release as distinct events.
  - Repository review (2026-11-12) found no document lifecycle/audit migrations, models, services, or APIs. Owner directed continuation; implementation scope must respect the still-open D-017 authority details.
  - Added append-only audit events for document, code, version/file, review, approval, date proposal/confirmation, release, and supersession actions. Implemented source-defined QMR review; Department Head then Service Chief review on policy/specific-guideline templates; MCC final approval for manual/procedure/guideline types; Process Owner effective-date proposal followed by KMT confirmation and release on/after that date. Unsupported approval routes remain blocked. D-017 remains open for unresolved document-type authority and form routing.
  - Added lifecycle/audit APIs, scoped timeline and actions in the registry, and migration files `000008` and `000009`. Migrations have not been run. Verification: PHP syntax checks for app/migrations/routes, frontend production build, and `git diff --check` passed (2026-11-12). Automated tests were not run.
- [x] 3.6 GATE: Phase 3 approved (owner ticks this after Task 3.5 is implemented and reviewed).

## Phase 4: Workflow engine (LOCKED until Phase 3 approved)
Scope: workflow templates/versions, sequential and parallel stages as approved, task
assignment and decisions, return/revision, delegation, separation of duties,
final approval and separate release.
- [x] 4.0 Break into small tasks and get my approval
- [x] 4.0.1 GATE: Owner approves the Phase 4 task breakdown below before implementation (owner approved all Phase 4 task scopes on 2026-11-12; task checkboxes track completion).
- [x] 4.1 Draft the workflow schema and authority mapping; no migrations. Cover template versions, stage patterns, workflow instances, assignments, decisions, and links to exact document versions. Use only ONP source-defined roles and preserve D-017 blocks; present the schema for owner review.
- [x] 4.1.1 GATE: Owner approves the concrete workflow schema before any Phase 4 migration or database implementation.
- [x] 4.2 Implement approved, versioned workflow templates and stage configuration. Enforce source-defined authority and prevent an in-progress instance from changing when a template is edited.
- [x] 4.3 Create a workflow instance when a version is submitted; resolve active, scoped assignees and retain the exact template version used. Keep unsupported document-type chains blocked.
  - Submission now selects the unique applicable published template version, creates the workflow/stage instances and role/scope task queues in the same transaction, verifies active assigned users, and records an audit event tied to the document. No matching supported template or no active eligible role assignment blocks submission. Migration generated but not run.
- [x] 4.4 Implement stage decisions, review/recommendation/approval records, return and resubmission, separation of duties, and append-only decision evidence. Keep final approval distinct from release.
  - Added append-only workflow decisions and task decision API. Active role/scope permission is rechecked at decision time; submitter/originator cannot decide; a final approver must differ from prior reviewers and MCC approval remains limited to manuals, procedures, and guidelines. Returns require a reason, close the current pass without deleting evidence, and allow resubmission on the same template version as a new pass. Workflow approval is recorded separately from KMT effective-date confirmation and release. Recommendation and release decisions remain unavailable under the source authority rules; release remains the separate lifecycle action. Legacy direct review/approval actions are hidden for workflow-backed versions. Migrations generated but not run.
- [x] 4.5 Implement delegation only where the original guideline or a recorded owner decision explicitly authorizes it; otherwise leave delegation unavailable and record the unresolved rule.
  - Verified fact: the ONP guideline and current owner decisions contain no explicit delegation or substitute-review authority. Delegation is unavailable; no delegation fields, API, or proxy decision behavior were added. D-017 remains open for unresolved approval authority.
- [x] 4.6 Integrate workflow tasks and decisions with the existing registry/lifecycle interface and enforce authorization on every API action.
  - The document registry returns only current-user tasks for versions they can view and act on; the UI records review, approval, or reasoned return through the workflow decision API. The API rechecks active account, document visibility, task stage/status, role permission, exact scope, assignment, author separation, and prior decisions. No system-administrator bypass is provided.
- [x] 4.7 Verify workflow and authorization acceptance criteria with automated tests; document limitations and present Phase 4 for owner review.
  - Automated verification: PHPUnit 6 tests passed with 19 assertions using local PHP 8.5.12 and SQLite; frontend `npm.cmd run build` passed; `php artisan route:list --path=workflow --except-vendor` listed the workflow configuration and decision routes; PHP syntax checks and `git diff --check` passed. Migrations were not run. Limitations: delegation, recommendation decisions, unsupported approval routes, and KMT release remain unavailable or separate as documented under D-017. Phase 4 was approved by the owner on 2026-11-16 (see 4.8).
- [x] 4.8 GATE: Phase 4 approved by the owner (2026-11-16).

## Phase 4B: Submission validation, approval-flow alignment, and role-based access (owner-approved; 4B.8 gate remains open)
Scope: validate supported submissions, add KMT verification and the approved type-specific workflow, and enforce role- and unit-scoped access on dashboards, pages, and every API route. D-029 to D-031 were approved on 2026-11-16; the owner approval gate at 4B.8 remains open.
- [x] 4B.0 Confirm scope and decisions. Owner decisions recorded on 2026-11-16 as D-029 to D-031 in `governance/OPEN_DECISIONS.md`.
  - D-029 applies the new flow only to manuals, procedures (SOPs), and ordinary guidelines selected by explicit per-document-type route setting. Policies and specific-guideline templates retain their source-defined review chain, distinguished through an explicit per-document-type route setting. Internal/external forms and resolutions remain outside this flow pending D-017. QMR is excluded from the new flow. Same-account KMT verification and release defaults to disallowed; an enabled setting permits it with separate audit events, and setting changes record actor and time.
  - D-030 sets title size to 14 pt by default, editable by KMT with setting version history; this resolves the title-size issue in D-023.
  - D-031 retains D-010's five formats and defines extraction, correction, validation, alerts, and scan handling.
  - D-025 remains unchanged: KMT manages records and approves disposition. Implement disposition permission through role-based authorization so an RMIC role can be assigned later. RMIC confirmation is required before go-live; no RMIC records authority is granted by this note.
- [x] 4B.1 Define submission checks and extraction behavior for PDF, DOCX, XLSX, JPEG, and PNG (D-010; 50 MiB limit; no inline preview). Extraction must be local and offline; Phase 4B performs no OCR. Extract document codes from a PDF text layer, DOCX text, or XLSX cell text. A PDF with no extractable text layer is treated as scanned. JPEG/PNG files and scanned PDFs always require KMT manual code entry, recorded as manual. A new version may be saved as a draft before code assignment, but cannot enter the approval workflow until KMT assigns a code and the file matches it. Only KMT enters or assigns codes; submitters may update the file to match KMT's assigned code and resubmit. Specify behavior for missing/multiple candidates, malformed codes, filename/metadata mismatch, unreadable or encrypted files, and manual-entry review; preserve findings and rerun checks on resubmission.
  - Specification: `specifications/SUBMISSION_VALIDATION.md`.
- [x] 4B.2 Implement automatic checks and report pass/warning/block findings without silently altering the source. Apply file signature/type, size, readability, and exact-file SHA-256 duplicate checks to all five types. For PDF text-layer, DOCX, and XLSX submissions, check extracted code format, approved-list validity, global uniqueness/conflicts, and duplicate code; scanned PDFs and JPEG/PNG use KMT-entered codes for these code checks. Check title size (14 pt default) only when a title can be reliably identified and its formatting can be read: PDF text-layer font metadata, DOCX paragraph/run formatting, or XLSX cell formatting. Otherwise show exactly “not checked, KMT to verify manually”; never report an unevaluated formatting rule as pass. JPEG/PNG and scanned PDFs always receive that manual formatting status. A corrected extracted code reruns all applicable checks and is flagged for KMT.
- [x] 4B.3 Add KMT verification after automatic checks and before approval. KMT may verify or return for revision with a reason. Preserve prior findings and decisions; rerun checks on resubmission.
- [x] 4B.4 Implement the D-029 flow for manuals, procedures (SOPs), and ordinary guidelines selected by an explicit per-document-type route setting: Process Owner → KMT verification → Division Head review → MCC approval → Process Owner proposes the effective date → KMT confirms the proposed date → KMT release. Resolve the Division Head reviewer as the head of the division that owns the document, with a review permission scoped to that document and owning division. Keep policy and specific-guideline template routing source-defined; keep internal/external forms and resolutions blocked from this flow until D-017 resolves routing. QMR is not a stage in the new flow. Keep approval separate from release.
- [x] 4B.5 Add a configurable same-account KMT verify-and-release setting, default OFF. When ON, allow the same KMT account to perform both actions but record separate audit events. Log setting changes with the changing user and timestamp. Enforce authorization and unit/resource scope on every API route; direct URL/API access without authority must be refused.
- [x] 4B.6 Implement role-scoped dashboards, menus, pages, and actions. Recommend a combined dashboard for users with multiple active roles; show only authorized widgets, label unit scope, and keep server authorization authoritative. Process Owner: own-unit drafts, returned submissions, assigned tasks, and applicable effective-date proposal tasks. KMT: verification, returned submissions, registration/release, assigned distribution acknowledgments/exemptions, and records tasks under D-025. Keep disposition authorization role-based: KMT retains the current grant, and an RMIC role may be assigned later only after approved authority; obtain RMIC confirmation before go-live. Division Head: scoped assigned decisions for the D-029 types. MCC: approval tasks only for D-029 types. RMIC: no records authority unless separately assigned under a future approved role permission. System Administrator: accounts/settings only, with no document approval/release/distribution or disposition authority from admin status. Regular recipient staff: own released distributions and acknowledgment status; no training menu, registry, or other recipients' records by default. Scope all content to the user's authorized units/resources.
- [x] 4B.7 Update and run workflow, permission, API, dashboard, and submission tests; record results, failures, skips, and limitations. Include accepted-format and extraction edge cases; per-format checkability and manual-formatting status; code correction and rerun; KMT return/resubmission; exact D-029 routing and QMR exclusion; Division Head resolution and scope; separate approval/release; same-account setting OFF/ON and setting-change audit; multi-role dashboards; and server-side unit isolation. Add negative page and API tests: Process Owner denied release/out-of-unit access; KMT denied MCC/Division Head decisions; Division Head denied KMT registration/release/out-of-unit access; MCC denied KMT release/registration; RMIC denied records actions unless explicitly assigned; System Administrator denied business actions; recipient staff denied another recipient's acknowledgment, registry, and admin actions; multi-role users denied unassigned actions and units.
  - Final full automated suite: 27 tests passed, 201 assertions, 0 failed, 0 skipped (`backend/.local-tools/php-8.5.12/php.exe artisan test`, PHP 8.5.12; PHPUnit SQLite in-memory). Initial run had 2 failures caused by the test harness retaining `auth.session` across `actingAs` users; tests now isolate authorization by bypassing that unrelated session middleware, then the full suite passed. PHP syntax checks, `git diff --check`, and `npm.cmd run build` passed. Limitation: PDF code extraction currently reads simple uncompressed literal text only; compressed streams/font maps require KMT manual verification and a future local parser improvement.
- [ ] 4B.8 GATE: Phase 4B approved by owner (owner ticks this).

## Phase 5: Distribution (LOCKED until Phase 4 approved)
Scope: distribution targets, exact-version distribution, acknowledgment tracking,
training evidence where approved.
- [x] 5.0 Break into small tasks and get my approval (owner approved the Phase 5 task breakdown on 2026-11-16).
- [x] 5.1 Draft the Phase 5 data schema and authority map for distributions tied to exact released versions, unit/role/user targets, configurable acknowledgment, due dates, acknowledgments, exemptions, audit, and any approved training evidence. Keep paper-copy numbers, stamps, printouts, retrieval, shredding, and provisional issuance blocked under D-022. No migrations.
  - Added the Phase 5 distribution schema proposal and authority map to `architecture/DATABASE_SCHEMA.md`. It snapshots exact released versions and resolved recipients, separates receipt acknowledgment from training evidence, derives overdue from a local-date due date, and preserves audit evidence. D-022 paper-copy controls, acknowledgment defaults, and training evidence remain open. No migrations generated or run; owner schema approval is required at 5.1.1.
- [x] 5.1.1 GATE: Owner approved the concrete distribution schema on 2026-11-16. Training evidence is excluded from Phase 5 pending a separate definition. D-022 remains open; exemption authority/scope was resolved by D-024 on 2026-11-16.
- [x] 5.2 Implement approved distribution and recipient schema; preserve exact version links and prevent distribution of unreleased versions.
  - Added migration `2026_11_16_000013_create_distribution_tables.php` and models for distributions, target specifications, recipient snapshots, and recipient-to-target sources. Migration generated but not run.
- [x] 5.3 Implement authorized distribution creation and target resolution for organizational units, roles, and individual users; record append-only distribution audit evidence.
  - KMT `document.distribute` permission is rechecked on the server. Distribution issue requires a released exact version, snapshots active users from selected targets, deduplicates overlapping targets, and records audit evidence transactionally. Receipt acknowledgment is authenticated and restricted to the named recipient; no proxy authority is granted.
- [x] 5.4 Implement recipient acknowledgment, approved exemptions with reason, and pending/overdue status using the approved due-date rule. Keep acknowledgment distinct from training evidence.
  - Implemented recipient-only self-acknowledgment, KMT exemption for one pending recipient with a required reason, pending/not-required/acknowledged/exempted states, derived overdue status, and append-only audit evidence. Training evidence remains excluded; D-022 paper controls remain open.
- [x] 5.5 Implement the registry interface for distributing an exact released version and viewing recipient status; enforce authorization on every API action.
  - Added KMT distribution creation/history in the registry and a recipient distribution inbox with exact-version file access and self-acknowledgment. File download authorization also recognizes active recipients for the exact distributed version.
- [x] 5.6 Verify distribution and authorization acceptance criteria with automated tests; document limitations and present Phase 5 for owner review.
  - Verification passed: PHPUnit 11 tests / 45 assertions (local PHP 8.5.12 with SQLite); `npm.cmd run build`; PHP syntax checks for the distribution service/controller/routes/feature test; route listing confirms all five distribution routes including exemption; `git diff --check`. Migration remains unapplied. Limitations: paper-copy controls remain under D-022, training evidence is excluded, and authenticated receipt is not a legal e-signature under D-009. Phase 6 remains locked until owner approves Phase 5.
- [x] 5.7 GATE: Owner approved Phase 5 to proceed to Phase 6 (2026-11-16).

## Phase 6: Migration (LOCKED until Phase 5 approved)
Scope: batch upload, metadata mapping, duplicate/conflict detection, verification
queue, reconciliation report.
First migration test inputs: the ONP guideline documents and `reference/source_originals/CSAC_FRONT_PAGE.docx`. Imported documents are never auto-approved.
- [x] 6.0 Break into small tasks and get my approval (owner approved Phase 6 on 2026-11-16)
- [x] 6.0.1 GATE: Owner approved the Phase 6 scope on 2026-11-16. Task scopes: 6.1 schema; 6.2 batch and item API; 6.3 safe private file intake; 6.4 metadata capture, preservation, duplicate/conflict flags; 6.5 authorized verification and reconciliation report; 6.6 automated verification and review.
- [x] 6.1 Draft the migration batch/item schema and lifecycle; no database migrations run.
  - Drafted `document_migration_batches` and `document_migration_items`, preserving source metadata separately from normalized mappings, file hashes/private keys, candidate matches, verification evidence, and optional registered-document links. Generated migration `2026_11_16_000014`; not run against the project database.
- [x] 6.2 Implement batch creation, migration item listing, and source metadata preservation.
  - Added KMT-authorized batch APIs and migration queue UI. Captures original code/title/type/category/revision/approval/effective date/owner/department/source and historical-version relationship where supplied.
- [x] 6.3 Implement private supported-file intake with generated storage names, hashes, and size/type validation.
  - Added PDF/DOCX/XLSX/JPEG/PNG uploads up to 50 MiB each, private generated storage paths, SHA-256 verification, integrity-checked source downloads, and retention of source files.
- [x] 6.4 Implement normalized metadata mapping and duplicate/conflict detection without overwriting or auto-approving documents.
  - Mapping preserves original source values and recalculates title/code/hash candidate matches. Conflicts require reviewer notes; no current document is overwritten.
- [x] 6.5 Implement permission-checked verification queue and reconciliation counts.
  - KMT registration permission is checked server-side. Verification states, evidence, batch reconciliation counts, and distinct registration as an unreleased draft are implemented. Imported sources never acquire approval or release state; source revision labels are preserved as supplied.
- [x] 6.6 Verify authorization, file integrity, duplicate handling, and reconciliation; present Phase 6 for review.
  - Verification passed: PHPUnit 17 tests / 75 assertions (local PHP 8.5.12 with SQLite); frontend production build; PHP syntax checks; route listing confirms 10 migration routes; `git diff --check`. Project database migration remains unapplied. Phase 6 was approved by the owner on 2026-11-16.
- [x] 6.7 GATE: Phase 6 approved by owner (owner approved Phase 6 on 2026-11-16).

## Phase 7: Extended controls (LOCKED until Phase 6 approved)
Scope: external references, operational records and retention, reports,
notifications, backup monitoring.
- [x] 7.0 Break into small tasks and get my approval (owner approved Phase 7 on 2026-11-16)
- [x] 7.0.1 GATE: Owner approved Phase 7 on 2026-11-16. Task scopes: 7.1 external references; 7.2 operational-record and configurable retention schema; 7.3 safe retention/hold/disposition controls; 7.4 permission-checked reports; 7.5 local notifications; 7.6 backup planning deferral; 7.7 verification and review. D-011 proposal review remains open; D-013 backup setup is deferred to Phase 8; D-016 has a JSON summary/audit baseline. No patient records, automatic deletion, or inferred backup success.
- [x] 7.1 Implement external reference registry and document links with review dates/status.
  - Added KMT-authorized create/list/update and document-link APIs, plus a registry UI for issuing body, reference number, optional URL, publication/review dates, status, and notes. External updates never rewrite controlled documents.
- [x] 7.2 Draft and implement operational records and configurable retention schedule schema; do not seed unapproved periods.
  - Generated the Phase 7 schema extension and reversible migration for configurable retention schedules, operational-record metadata, hold/disposition evidence, user notifications, and backup-run evidence. No retention values or jobs were seeded; migration was not run. D-025 now authorizes KMT to manage records and disposition.
- [ ] 7.3 Implement legal holds and approval/evidence-gated disposition; no automatic deletion.
  - KMT record permission, operational-record metadata, hold placement/release, disposition request/decision/execution evidence, and audit are implemented. Holds block disposition and execution never deletes data. The source-based three-year proposal is documented in `governance/RETENTION_SCHEDULE_PROPOSAL.md`; per owner direction it remains inactive with no trigger proposed, pending D-011/D-022 review.
- [x] 7.4 Implement authorized reports with approved filters and export format limits; track D-016.
  - Added KMT-authorized record counts and filtered audit history (JSON API and Reports screen). Owner approved this JSON summary/audit baseline under D-028; further reports and exports are deferred.
- [x] 7.5 Implement in-app local notifications without external delivery services.
  - Added user-scoped notification inbox/read state and local in-app notification on exact-version distribution. No internet delivery service is used.
- [x] 7.6 Defer production backup schedule, destination, and monitoring to Phase 8 under owner decision D-027.
  - D-013 remains open for deployment; no backup job, evidence producer, or success monitor is active in Phase 7.
- [x] 7.7 Verify implemented authorization, retention safeguards, reports, and notifications; document backup deferral and present Phase 7 for owner review.
  - Verification passed: PHPUnit 18 tests / 88 assertions (local PHP 8.5.12/SQLite); frontend production build; PHP syntax and route-list checks for migration, records, external references, reports, and notifications; `git diff --check`. Migrations `000014` and `000015` were not applied to the project database. Backup configuration/monitoring is explicitly deferred to Phase 8; the three-year proposal awaits owner review under D-011/D-026.
- [x] 7.8 GATE: Phase 7 approved by owner (2026-11-16). Retention proposal remains inactive under D-011/D-026; production backup planning is deferred to Phase 8 under D-027.

## Phase 8: Verification and deployment (unlocked after Phase 7 approval)
Scope: functional, security, workflow, migration, backup/restore, usability testing;
UAT; migration rehearsal; deployment and admin docs; training; controlled go-live.
Test evidence format: `DCMS_AI_Development_Pack/governance/TESTING_AND_VERIFICATION.md`
- [x] 8.0 Break into small tasks and get my approval (owner approved the Phase 8 breakdown on 2026-11-16)
- [x] 8.0.1 GATE: Owner approved this Phase 8 task breakdown on 2026-11-16. Production deployment, project-database migrations, production backup/restore, and go-live remain separately gated.
- [ ] 8.1 Complete application security, authorization, workflow, distribution, migration, and usability verification; record evidence and defects.
  - Rerun recorded in `governance/PHASE8_TEST_EVIDENCE.md`: PHPUnit 22 tests / 145 assertions passed (0 failures/errors/skips); frontend build passed; lint exited 0 with 10 warnings. Localhost API/UI smoke passed and all six fictional accounts signed in. The manual cases in `governance/LOCALHOST_PHASE8_TEST_GUIDE.md` are available but have not been performed by the owner. ONP UAT, confidentiality/accessibility review, MySQL concurrency/performance, and production TLS/network checks remain open; 8.1 is not complete.
- [ ] 8.2 Prepare institution-approved UAT scenarios, execute UAT, capture acceptance and unresolved issues.
  - Draft scenario set is in `governance/PHASE8_UAT_SCENARIOS.md`; localhost-only fictional manual steps are in `governance/LOCALHOST_PHASE8_TEST_GUIDE.md`. Institutional UAT execution and acceptance remain pending named ONP testers and an approved UAT environment.
- [ ] 8.3 Finalize deployment architecture and production server plan after D-007; document network, TLS, local account recovery, monitoring, and operations.
  - Reviewable planning proposal prepared in `governance/PHASE8_DEPLOYMENT_PROPOSAL.md`; D-007 remains open pending ONP confirmation of target, network, support, and actual capacity. No deployment target is configured.
- [ ] 8.4 Approve backup schedule, destination, RPO/RTO, retention, and restore procedure under D-013; implement and verify database plus private document-file backup/restore.
  - Reviewable candidate policy prepared in `governance/PHASE8_DEPLOYMENT_PROPOSAL.md`; D-013 remains open pending destination, business recovery targets, retention review, and custody decisions. No backup job or restore evidence exists.
- [x] 8.5 Rehearse staging import using the supplied ONP guideline DOCX documents and `reference/source_originals/CSAC_FRONT_PAGE.docx`; reconcile results and resolve owner decisions before production import.
  - Isolated SQLite/fake-private-storage rehearsal passed for all 7 supplied DOCX source files: filenames and SHA-256 hashes matched and every item remained pending verification. Reconciliation reported 7 pending / 0 verified. No metadata approval, live registration, or production import occurred; unresolved D-011/D-022/D-007/D-013 remain outside this rehearsal.
- [ ] 8.6 Complete administrator/user documentation, training materials, and role-based staff training.
  - Drafts created: `governance/DEPLOYMENT_AND_RECOVERY_RUNBOOK.md` and `governance/USER_TRAINING_GUIDE.md`. Institution-specific deployment settings and actual staff training remain pending.
- [ ] 8.7 Conduct go-live readiness review; obtain explicit owner approval before production deployment or migration execution.
- [ ] 8.8 GATE: Phase 8 verified and controlled go-live approved by owner.

## Parking lot (ideas, NOT approved, do not build)
- (empty)
