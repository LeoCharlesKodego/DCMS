# Functional Requirements

Requirement IDs are stable references for planning and testing. "Must" indicates a required capability for the intended product unless later changed by an approved decision.

## FR-01 Institution configuration
- FR-01.1 The system must store institution identity, contact details, facility type, logo, timezone, and status.
- FR-01.2 Institution-specific settings must be configurable without code changes.
- FR-01.3 A deployment may begin with one institution. Multi-institution tenancy is an unresolved architectural decision.
- FR-01.4 Institution data must be isolated from other institutions if multi-tenancy is enabled.

## FR-02 Organizational structure
- FR-02.1 Administrators must configure divisions, departments, sections, units, and offices.
- FR-02.2 Organizational units must support parent-child relationships.
- FR-02.3 Users may have multiple organizational assignments where authorized.
- FR-02.4 Organizational hierarchy must not be treated as the approval hierarchy automatically.

## FR-03 User and access management
- FR-03.1 Administrators can create, deactivate, and manage user accounts.
- FR-03.2 Users can have multiple roles with organizational scope and validity periods.
- FR-03.3 Permissions must support actions and scope.
- FR-03.4 Authorization must be enforced server-side.
- FR-03.5 Technical administrator access must not imply approval authority or unrestricted confidential-document access.

## FR-04 Document classification and registry
- FR-04.1 Institution-authorized users can configure document types and hierarchical categories.
- FR-04.2 The registry stores a document's identity, code, title, owner, owning unit, classification, confidentiality, and lifecycle status.
- FR-04.3 Document numbering formats and sequences must be configurable.
- FR-04.4 Existing codes must be preservable during migration.
- FR-04.5 Duplicate-code rules must be configurable and must not silently alter imported codes.

## FR-05 Document creation and revision
- FR-05.1 Authorized users can create a document record and submit a draft file or supported source.
- FR-05.2 The system must capture change summaries for revisions.
- FR-05.3 A revision must create a new version, not overwrite a released version.
- FR-05.4 The system must support multiple files or renditions for a version where required.
- FR-05.5 Document owners and responsible departments must be recorded.

## FR-06 Configurable workflow engine
- FR-06.1 Authorized administrators can create workflow templates.
- FR-06.2 Templates can define ordered or parallel steps, assigned roles/users/organizational units, required actions, due dates, and mandatory status.
- FR-06.3 Workflow templates can be associated with document types, categories, or other configured conditions.
- FR-06.4 Each workflow execution must be stored as an instance and retain the template version used.
- FR-06.5 A workflow must support return for revision, rejection, cancellation, delegation, and escalation where configured.
- FR-06.6 The system must validate that the assigned person has the required authority at the time of action.
- FR-06.7 The system must prevent prohibited self-approval and separation-of-duty conflicts.
- FR-06.8 Final approval and controlled release must be separate actions.
- FR-06.9 Changes to a workflow template must not silently alter in-progress workflow instances.

## FR-07 Review and approval
- FR-07.1 Reviewers can record decisions and comments.
- FR-07.2 Decisions must identify actor, timestamp, task, workflow instance, and document version.
- FR-07.3 The system must distinguish review, recommendation, approval, and release.
- FR-07.4 Rejected or returned documents must retain decision history.
- FR-07.5 Approval evidence must be protected from unauthorized alteration.

## FR-08 Version control and release
- FR-08.1 Each version must have a unique identifier within its document.
- FR-08.2 A released version must be immutable to ordinary users.
- FR-08.3 The system must record approval date, effective date, release date, and supersession/withdrawal events separately.
- FR-08.4 A future effective date may be configured.
- FR-08.5 Only authorized roles may release, withdraw, or supersede controlled versions.
- FR-08.6 Historical versions must remain retrievable subject to access and retention policies.

## FR-09 Distribution and acknowledgment
- FR-09.1 A distribution must identify the exact document version.
- FR-09.2 Distribution targets may be organizational units, roles, or individual users.
- FR-09.3 Acknowledgment requirements must be configurable.
- FR-09.4 The system must track pending, acknowledged, exempted, and overdue recipients.
- FR-09.5 Acknowledgment must not be represented as training or competency evidence.
- FR-09.6 Training records may be linked to a document version.

## FR-10 External references
- FR-10.1 Authorized users can register external standards, laws, regulations, and guidance.
- FR-10.2 Internal documents can be linked to external references.
- FR-10.3 External-reference review dates and status can be tracked.
- FR-10.4 External changes must not automatically rewrite or replace internal controlled documents.

## FR-11 Records and retention
- FR-11.1 Authorized users can register in-scope operational records.
- FR-11.2 Record types must be linked to institution-approved retention schedules.
- FR-11.3 Retention calculations must support configurable trigger events and time units.
- FR-11.4 Legal holds and other restrictions must block routine disposal.
- FR-11.5 Disposal requires authorized approval and evidence.
- FR-11.6 Patient health records are outside the ordinary DCMS record scope.

## FR-12 Existing-document migration
- FR-12.1 Users can create migration batches and import supported files in bulk.
- FR-12.2 The system must preserve original filenames, codes, revision labels, dates, owners, departments, and source metadata when provided.
- FR-12.3 The system must retain source files and integrity hashes.
- FR-12.4 Migration items must have verification statuses.
- FR-12.5 Duplicate, conflicting, incomplete, obsolete, and superseded items must be flagged for authorized resolution.
- FR-12.6 Upload or migration must never imply approval.
- FR-12.7 The system must generate a migration report.

## FR-13 Audit and reporting
- FR-13.1 Important events must be logged, including creation, edits, submission, decisions, release, access where configured, distribution, acknowledgment, migration, and disposition.
- FR-13.2 Audit entries must identify actor, time, action, target record, and relevant before/after data where appropriate.
- FR-13.3 Authorized users can filter and export reports.
- FR-13.4 Audit records must not be editable by ordinary application users.

## FR-14 Notifications
- FR-14.1 Notifications can be triggered by assignments, due dates, decisions, release, review schedules, and acknowledgment deadlines.
- FR-14.2 Notification rules and recipients must be configurable.
- FR-14.3 Core local notifications must work without internet.

## FR-15 Backup and recovery
- FR-15.1 Backups must include database and document files.
- FR-15.2 Backup jobs must record outcome, time, destination, and integrity information.
- FR-15.3 Restoration must be testable and documented.
- FR-15.4 Backup status must not be reported as successful without evidence.
