# Non-Functional Requirements

## Security
- Enforce authentication and authorization on every protected server-side operation.
- Apply least privilege and separation of duties.
- Protect credentials using a modern password-hashing mechanism.
- Use secure session handling, CSRF protection where applicable, input validation, and output encoding.
- Prevent insecure direct object references and unauthorized file access.
- Validate uploads and store files outside executable/public paths.
- Protect secrets through environment configuration, not source control.
- Record security-relevant events without logging passwords, tokens, or unnecessary sensitive content.

## Privacy and data minimization
- Keep patient health records outside the DCMS scope.
- Collect only data required for document control and operational evidence.
- Apply confidentiality classification and access restrictions.
- Define retention and disposal rules with applicable legal and institutional review.
- Do not expose sensitive documents through public links.

## Integrity and traceability
- Released files must be protected against silent replacement.
- Store cryptographic hashes for controlled files.
- Use transactions for multi-record state transitions.
- Preserve historical approvals and revisions.
- Audit logs should be protected against ordinary application-level modification.
- Record time consistently and display it using the institution's configured timezone.

## Availability and recovery
- Core workflows must operate on the local network without internet.
- Provide documented backup and restore procedures.
- Back up both database and document storage.
- Define recovery point and recovery time objectives with the institution before production.
- Test restoration periodically.

## Performance and scale
- Establish measurable performance targets after expected user count, file volume, and server hardware are known.
- Paginate large registries and audit logs.
- Avoid loading entire document libraries or histories into memory.
- Use indexes based on observed query patterns.
- Large file uploads should provide clear progress and error handling.

## Usability and accessibility
- Provide clear statuses and next actions.
- Show users only actions they are authorized to perform.
- Distinguish drafts, approved documents, released copies, obsolete versions, and uncontrolled copies visually and textually.
- Provide useful validation messages.
- Support common desktop browser sizes; mobile support requirements remain to be confirmed.

## Maintainability
- Keep modules and business rules clearly separated.
- Use documented API contracts and database migrations.
- Provide automated tests for critical workflows and authorization.
- Avoid institution-specific hard-coded values.
- Maintain setup, backup, restore, and administrator documentation.
