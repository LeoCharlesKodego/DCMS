# Roles, Permissions, and Authority Model

Roles are configurable starting points, not fixed legal positions. Institutions must map actual job titles and delegated authorities.

## Suggested roles

| Role | Typical scope | Typical capabilities |
|---|---|---|
| System Administrator | Technical system | System configuration, accounts, technical maintenance |
| QMS Administrator | Institution-wide QMS | QMS settings, document-control configuration, oversight |
| Document Controller | Authorized document scope | Registration, metadata checks, controlled release, distribution, archive |
| Department Head | Assigned organizational unit | Department review and decisions within delegated authority |
| Document Coordinator | Assigned organizational unit | Draft preparation, submission, metadata maintenance |
| Technical Reviewer | Assigned subject area | Technical/clinical review and recommendation |
| Approving Authority | Delegated authority scope | Final approval within assigned document types/scope |
| General Personnel | Assigned access scope | View released documents, acknowledge, access assigned training |
| Internal Auditor | Authorized audit scope | Read audit evidence and records; no alteration of controlled content |

## Permission dimensions

Permissions should be evaluated using:
- subject: user and active role assignment;
- action: view, create, edit, submit, review, recommend, approve, release, distribute, withdraw, archive, export, configure;
- resource: document, version, workflow, record, migration batch, report;
- scope: institution, organizational branch, department, assigned documents, individual record;
- classification: public/internal/confidential/restricted or institution-configured levels;
- validity: assignment start and end dates.

## Separation of duties

- Technical administration does not automatically confer content approval.
- A document author should not approve their own document when policy requires independent approval.
- A reviewer recommendation is not final approval unless the configured authority explicitly says so.
- Release is a controlled action distinct from final approval.
- Delegation must be time-limited, scoped, authorized, and auditable.
- The system must validate authority at the time of action, not merely when a task was assigned.

## Authority matrix

Do not hard-code one universal approval matrix. The institution configures approval authority by document type, category, organizational scope, and workflow template. A future authority matrix should record who can review, recommend, approve, release, withdraw, and authorize disposal.
