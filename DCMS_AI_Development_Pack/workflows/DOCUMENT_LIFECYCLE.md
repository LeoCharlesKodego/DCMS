# Controlled Document Lifecycle

## Lifecycle stages

1. Need identified
2. Request initiated
3. Draft prepared or source file uploaded
4. Registration metadata checked
5. Department review
6. Technical/quality review
7. Final approval
8. Document registration finalized
9. Effective date assigned
10. Controlled release
11. Distribution
12. Acknowledgment and/or training
13. Periodic review
14. Revision, withdrawal, or continued validity
15. Supersession and archive

## Status concepts

Keep document status, version status, workflow status, and distribution status separate. Do not overload one status column to represent all processes.

Possible version statuses:
- draft
- submitted
- in_review
- returned
- approved
- scheduled
- released
- superseded
- withdrawn
- archived

Possible document-level status:
- active
- under_revision
- withdrawn
- archived

The actual status model must be normalized and transitions enforced in domain logic.

## Approval versus release

Approval means the authorized authority has accepted the version. Release means the institution's controlled-document process has made that approved version available for use. They are distinct events and may occur at different times.

## Effective dates

- Record approval, effective, and release timestamps separately.
- Support future effective dates if approved.
- Prevent distribution as the current effective controlled version before its effective date unless the institution explicitly configures a permitted pre-release access process.

## Revision and supersession

- Create a new version for each revision.
- Record revision reason and change summary.
- Preserve old approved versions.
- When a new version becomes effective, record the prior version's supersession event.
- Notify affected recipients and collect new acknowledgments/training when required.

## Withdrawal

Withdrawal must require authorized action, a reason, effective timestamp, affected distribution handling, and audit evidence. The system should retain historical evidence.
