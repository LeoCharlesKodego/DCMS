# Testing and Verification Standard

## General rule

A feature is not complete because code was written or a page renders. Completion requires relevant tests and observed evidence.

## Required test categories

### Functional
- Create, edit, submit, review, approve, release, revise, withdraw, and archive documents.
- Validate permitted and prohibited workflow transitions.
- Test due dates, future effective dates, and notifications.
- Test distribution and acknowledgment.

### Authorization and security
- Test every role and organizational scope.
- Attempt unauthorized API access directly.
- Test cross-department access restrictions.
- Test confidential document access.
- Test self-approval and separation-of-duty restrictions.
- Test file download authorization and insecure direct object references.
- Test upload validation and malicious filenames.

### Version integrity
- Confirm released versions cannot be silently overwritten.
- Confirm revisions preserve previous versions.
- Verify file hashes before and after storage operations.
- Confirm distributions point to exact versions.

### Workflow engine
- Sequential workflow.
- Parallel review.
- Multiple eligible assignees.
- Reassignment/delegation.
- Return for revision.
- Rejection and cancellation.
- Template changes while an instance is in progress.
- Approver role changes or expiry during a pending task.

### Migration
- Bulk import.
- Duplicate codes and identical file hashes.
- Missing metadata.
- Conflicting dates and revision labels.
- Missing approval evidence.
- Obsolete and superseded files.
- Source/destination hash reconciliation.
- Migration report accuracy.

### Records and retention
- Retention trigger calculations.
- Legal hold blocking disposal.
- Approval and rejection of disposal.
- Disposition evidence and audit event.

### Backup and recovery
- Backup database and files.
- Verify backup integrity.
- Restore to a test environment.
- Confirm restored files and hashes.
- Confirm application consistency after restore.

## Test evidence

For each test, record:
- test ID;
- requirement ID;
- preconditions;
- steps;
- expected result;
- actual result;
- pass/fail/blocked;
- environment;
- date and tester;
- evidence/log reference.

## Release gate

Do not declare a phase complete while critical tests are failing, security checks are unverified, or required acceptance criteria remain unmet. Clearly identify tests not run.
