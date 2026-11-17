# Deployment and Recovery Runbook (Draft)

**Status: Non-production draft. Production design is not approved.** D-007 (server OS/hardware), D-013 (backup schedule/destination/RPO/RTO), D-011 (retention), and D-022 (paper/digital controls) remain open or deferred. No server, backup destination, service account, database, or production environment is configured by this document.

See `PHASE8_DEPLOYMENT_PROPOSAL.md` for the reviewable D-007/D-013 planning options. The proposal does not approve or configure production infrastructure.

## Before deployment planning

- Project Owner resolves server OS/hardware and confirms the deployment/network boundary (D-007).
- Project Owner and technical administrator approve backup cadence, destination, RPO/RTO, encryption/access, retention, and restore test schedule (D-013).
- Complete migration reconciliation, UAT sign-off, user/role assignments, and retention/paper-copy policy review.
- Record the code revision, dependency state, migration list, seeders, environment variable names, storage paths, network design, certificates, and recovery contact roles. Store secret values outside source control and this document.
- Confirm sufficient private storage and upload limits for ONP source documents; test capacity and permission isolation.

## Deployment sequence (future approved change)

1. Approve an environment-specific change ticket, maintenance window, and rollback owner.
2. Verify a recent, successful, integrity-checked backup of both the database and private document files; record the recovery point.
3. Deploy the reviewed application build into the approved private network; configure TLS, local authentication, session settings, mail/queue choices, logging, and least-privilege filesystem access.
4. Review and apply migrations in the approved order only after backup verification; execute seeders only when their data is approved.
5. Bootstrap named administrators using the documented secure process; assign ONP roles and scopes; verify system administrators do not receive document authority by default.
6. Run health, login, permission, file integrity, workflow, exact-version distribution, reporting, and audit smoke checks.
7. Restore a copy into a non-production environment and verify database consistency, representative file hashes, user access, and workflow state.
8. Obtain explicit Project Owner go-live approval before opening production access or importing live institutional data.

## Recovery sequence (future approved change)

1. Declare the incident and restrict writes; preserve logs and evidence.
2. Select the latest verified recovery point that meets the approved RPO; confirm the corresponding database and private-file backup set.
3. Restore both database and files into an isolated environment first. Do not overwrite production during validation.
4. Verify migration/schema state, representative SHA-256 hashes, registry/version relationships, audit history, access controls, and application health.
5. Obtain the authorized recovery decision; switch service only under the approved change plan.
6. Record outage times, recovery point, integrity results, data loss (if any), approvals, and corrective actions.

## Rollback and migration cautions

- Do not reverse data-bearing audit, distribution, migration, or extended-control migrations after operational records exist. Use the reviewed recovery plan instead.
- Do not drop or overwrite document files as a rollback shortcut.
- A code rollback is allowed only after compatibility with the current database schema is verified.
- The current project database has not had the generated Phase 5-7 migrations applied. This document does not authorize applying them.
- Retention expiry never authorizes automatic deletion. Legal holds and disposition approvals remain mandatory.

## Operator record

For each future deployment/restore, capture: change ID; environment; approver; operators; application revision; migration/seed list; backup IDs and hashes; start/end times; smoke-test results; UAT/go-live acceptance; rollback decision; and unresolved defects.
