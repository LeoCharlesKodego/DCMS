# Mandatory Instructions for AI Coding Assistants

You are assisting with the development of the Institutional Document Control Management System (DCMS). These instructions are mandatory.

## 1. First responsibility: understand before coding

- Read this entire documentation pack before proposing implementation.
- Do not start coding, create migrations, modify files, install packages, or restructure the repository during project intake.
- First provide a concise understanding of the product, modules, roles, workflows, architecture, constraints, and unresolved decisions.
- Identify contradictions, missing information, and technical risks.
- Ask only questions that materially affect correctness. Do not repeatedly ask questions already answered in the project files or conversation.
- Do not treat an AI suggestion as an approved requirement.

## 2. No hallucination; evidence required

- Never invent existing files, functions, database tables, routes, APIs, configuration values, test results, or regulatory requirements.
- Inspect the actual repository before describing its implementation.
- Distinguish clearly between: verified fact, assumption, recommendation, and unresolved decision.
- If a file or command cannot be inspected or executed, say so.
- Never claim a test passed unless it was actually run and its result was observed.
- Do not claim ISO, legal, privacy, or regulatory compliance without a documented requirements mapping and verification evidence.
- When external legal or standards information is needed, use current authoritative sources and record source title, issuing body, edition/date, URL, access date, and applicability.

## 3. Preserve the project

- Before editing, inspect repository structure, package manifests, current architecture, relevant files, and Git status.
- Preserve existing working functionality, naming conventions, UI patterns, workflows, and architecture unless the user approves a change.
- Do not perform unrelated rewrites, broad refactors, mass formatting, or dependency upgrades.
- Do not delete tables, files, data, tests, or working code as a shortcut.
- Do not change database schema or run destructive commands without explicit approval and a verified backup.
- Do not install new packages, frameworks, or services without explaining why and obtaining approval.
- Keep changes limited to the requested scope.

## 4. Work in controlled phases

For every task:
1. Restate the requested outcome and scope.
2. Inspect relevant files and dependencies.
3. Identify exact files likely to change.
4. Explain the proposed approach and risks.
5. Wait for approval when the change affects architecture, database structure, security, data, dependencies, or external services.
6. Implement the smallest coherent change.
7. Run relevant tests, static checks, and build commands.
8. Inspect the resulting diff.
9. Report changed files, commands run, actual results, unresolved issues, and rollback considerations.

Do not combine multiple major modules into one unreviewable change.

## 5. DCMS domain rules

- DCMS controls institutional documented information. It is not an Electronic Medical Record (EMR) or patient charting system.
- Do not store patient medical histories, prescriptions, laboratory patient results, or patient charts as ordinary DCMS records.
- The system may control procedures and policies that govern patient records.
- An uploaded or migrated file is not automatically an approved controlled document.
- Approval, authorization, effective date, and release are distinct events.
- Released versions must not be silently edited or replaced.
- New revisions must preserve links to previous versions.
- Distribution must identify the exact version distributed.
- Acknowledgment is not automatically proof of training or competency.
- Retention expiry does not automatically authorize destruction.
- Migration must preserve source metadata and original files wherever practical.
- System administrator privileges must not automatically grant document approval authority or access to confidential content.
- Workflows, organization structures, document classifications, numbering conventions, and authority assignments must be configurable per institution.

## 6. Security and data integrity

- Enforce authorization on the server/API, not only by hiding UI controls.
- Apply institution, organizational-unit, role, document-classification, and record-level scopes as applicable.
- Validate uploaded file type, size, content handling, and storage destination.
- Use generated storage names; do not trust user-supplied paths or filenames.
- Store file hashes and verify integrity when copying or restoring files.
- Protect audit records from ordinary application users.
- Do not expose secrets, credentials, private keys, or sensitive data in logs or client code.
- Use secure password hashing and session management.
- Prevent insecure direct object references and unauthorized file downloads.
- Design backup and restoration for both database data and document files.

## 7. Technical discipline

- Treat `architecture/TECHNICAL_DECISIONS.md` as provisional until the user approves it.
- Do not introduce an additional framework or architecture pattern without approval.
- Keep business logic out of presentation components.
- Use database transactions for multi-step operations that must remain consistent.
- Use foreign keys and constraints for data integrity where appropriate.
- Make workflow transitions explicit and validated.
- Avoid hard-coded institution-specific department names, approval chains, document codes, or retention periods.
- Use migrations that are reversible where practical; document irreversible operations and require approval.
- Keep environment-specific configuration out of source control.

## 8. Completion report format

At the end of each implementation task, report:

- **Outcome:** what was completed.
- **Files changed:** exact paths and purpose.
- **Database changes:** migrations and data impact, if any.
- **Verification:** exact commands run and observed results.
- **Security considerations:** relevant checks.
- **Known limitations:** what remains incomplete.
- **Rollback:** how to reverse the change, where applicable.

Never say "complete", "working", or "passed" beyond the evidence obtained.

## 9. User authority

The user owns all project decisions. The AI may recommend improvements but must not silently:
- change scope or workflows;
- change technology stack;
- alter approval authority;
- change document numbering or retention policy;
- delete or overwrite existing data;
- deploy, publish, send, or expose the system externally.

When a requirement is ambiguous, propose a safe interpretation and ask one focused question if the ambiguity blocks progress.
