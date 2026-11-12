# System-Wide Business Rules

1. Every controlled document belongs to an institution.
2. Every controlled document has an owner and responsible organizational unit.
3. Document numbering conventions are institution-configurable.
4. Existing document identifiers can be preserved during migration.
5. A file upload does not make a document approved or controlled.
6. Draft, submitted, reviewed, approved, effective, released, superseded, withdrawn, and archived are distinct lifecycle states or events.
7. Final approval and controlled release are separate.
8. A released version cannot be silently modified or replaced.
9. A revision creates a new version and preserves the old version.
10. Every distribution identifies the exact released version.
11. Acknowledgment is not equivalent to training or competency.
12. Workflow templates are configurable and may vary by document type.
13. In-progress workflow instances retain the workflow definition/version with which they started.
14. Approval authority is checked at decision time.
15. Separation-of-duty restrictions must be enforced.
16. A user's organizational reporting line does not automatically establish document approval authority.
17. System administrator privileges do not automatically grant document approval or confidential-content access.
18. External-reference updates do not automatically alter internal controlled documents.
19. Retention expiry does not automatically authorize disposal.
20. Legal holds and restrictions prevent routine disposition.
21. Migration preserves source evidence and flags uncertainty for authorized verification.
22. Audit events are generated for important state changes.
23. Database and file backups are both required.
24. A backup is not considered verified until integrity and restoration checks have been performed.
25. Patient health records are outside the ordinary DCMS scope.
