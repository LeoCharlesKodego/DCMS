# Phase 3 Database Schema Draft

**Status: Owner-approved as the Phase 3 implementation basis (2026-11-12), with D-019 through D-021 now decided.**

This proposal translates the approved conceptual model and Phase 3 scope into a relational schema for document types/categories, registry identity, code assignment, versions/files, lifecycle history, and document audit. The owner approved this draft by directing that task 3.2 proceed. D-019 through D-021 govern code structure, revision labels, uniqueness, and source conflicts. The schema approval does not authorize running migrations.

## Design boundaries

- One ONP installation: no tenant or institution key is proposed on each row.
- A document is the stable registry identity. A version is a particular revision. A file belongs to a version.
- Type codes, document codes, revision labels, and classification values are stored as configurable or source-provided strings. Do not encode ONP code rules in database enums or application logic.
- A file upload, registration, approval, effective date, and release are separate facts. Phase 3 must not make upload imply approval or release.
- The existing Phase 2 application currently has a `users` table, but no organizational-unit model or migration. Owner and unit foreign keys below depend on confirming that Phase 2 implementation before generating migrations.
- Phase 4 will define workflow and approval authority. Phase 5 will define distribution and physical-copy handling. Those modules are not silently introduced by this draft.

## Proposed tables

Column types are MySQL-oriented; exact lengths, collations, indexes, and deletion rules remain subject to schema review. Every table has a numeric primary key and `created_at` / `updated_at` timestamps unless specified otherwise.

### `document_types`

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `code` | varchar | Nullable, configurable code as recorded |
| `name` | varchar | Required display name |
| `description` | text | Nullable |
| `is_active` | boolean | Defaults true; historical records retain their type |

Do not infer coding-list type values from configurable document type labels. Preserve the Annex 1/2 type labels and the separate coding-list terms as source values; the Master Code Register uses the official coding list.

### `document_categories`

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `parent_id` | unsigned bigint, nullable | Self-reference to `document_categories.id`; null means root |
| `name` | varchar | Required display name |
| `description` | text | Nullable |
| `is_active` | boolean | Defaults true; referenced categories are deactivated, not deleted |

The hierarchy is configurable. No fixed category names or department names are seeded here. Whether category names must be unique within a parent is left for review.

### `document_classifications`

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `code` | varchar | Configurable value |
| `name` | varchar | Required display name |
| `description` | text | Nullable |
| `is_active` | boolean | Defaults true |

This lookup provides the classification required by FR-04.2 without hard-coding labels such as public, internal, confidential, or restricted. Access enforcement rules remain part of authorization work and must not be inferred from this lookup alone.

### `documents`

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Stable registry identity; primary key |
| `document_type_id` | unsigned bigint | Required FK to `document_types.id` |
| `category_id` | unsigned bigint, nullable | FK to `document_categories.id` |
| `classification_id` | unsigned bigint, nullable | FK to `document_classifications.id`; null only while incomplete/draft if approved |
| `current_revision_label` | varchar | Major.Minor label; starts at `1.0` and is displayed separately from the base code |
| `title` | varchar | Required registry title |
| `owner_user_id` | unsigned bigint | Required FK to the confirmed user table; implements owner requirement |
| `owning_unit_id` | unsigned bigint | Required FK to the confirmed organizational-unit table |
| `registered_at` | timestamp, nullable | When registration was recorded, matching the ONP register |
| `registered_by_user_id` | unsigned bigint, nullable | FK to the registering user; actual authority is enforced by Phase 4/role configuration |
| `effective_date` | date, nullable | Current version's assigned effective date, mirrored for register display |
| `status` | varchar | Current document-level lifecycle state, validated by domain logic |
| `created_by_user_id` | unsigned bigint | FK to user who created the record |
| `updated_by_user_id` | unsigned bigint, nullable | FK to last metadata editor |

Document-level states include the initial registration states `pending_code` and `registered`, then `active` and `under_revision`; withdrawal/archive transitions require their source-defined authority. Status remains separate from version and workflow state.

The Document Control Register is represented by `documents`; the Master Code Register is a separate table. A registered document obtains its base code through its one-to-one Master Code Register entry. The displayed identifier appends `Rev {current_revision_label}`; the base code excludes revision. Enforce global uniqueness on base codes and on (`code_prefix`, `sequence_number`). Store code components exactly as written in the official lists. Preserve source duplicates with conflict flags and block conflicted components from new assignments.

### `document_code_components`

Stores source list rows for Service, Section/Unit, and Document Type code components. Each row retains its source group and source flags. Source code values need not be unique in this table because duplicate rows must be preserved. Rows whose values conflict globally are flagged and inactive for new assignments. Do not correct, normalize, or replace source values.

### `document_code_sequences`

| Column | Proposed type | Notes |
|---|---|---|
| `code_prefix` | varchar | Unique prefix composed from approved components, without sequence |
| `next_sequence` | unsigned small integer | Next number to allocate, from 1 through 999 |

Allocate under a transaction and row lock. Sequence labels are zero-padded to three digits. The Master Code Register also has unique constraints preventing duplicate prefix/sequence pairs and globally duplicate base codes.

### `master_code_register_entries`

Stores each KMT code assignment independently of the Document Control Register. It records the selected service, section/unit, optional unit component, document-type component, code prefix, allocated sequence, exact base code, process owner, assigning KMT user, and assignment time. A code entry links to at most one document; a document links to at most one entry.

### `document_versions`

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `document_id` | unsigned bigint | Required FK to `documents.id` |
| `sequence_no` | unsigned integer | Internal ordering only; unique with `document_id` |
| `revision_label` | varchar | Major.Minor label under D-020; starts at `1.0`, advances by `.1`, or to the next major `.0` |
| `proposed_effective_date` | date, nullable | Date proposed by the Process Owner after approval; KMT must confirm the same date |
| `effective_date` | date, nullable | Date confirmed by KMT separately from approval and release |
| `version_code` | varchar, nullable | Exact full code printed/recorded for this version, if it differs by revision; never parsed or reconstructed |
| `change_summary` | text, nullable | Change reason/summary for this version |
| `status` | varchar | Current version state; separate from document/workflow state |
| `created_by_user_id` | unsigned bigint | FK to user who created the version |

Uploaded versions start in `draft`. The lifecycle service enforces submission, review, return, approval, date proposal, KMT date confirmation, scheduling, release, and supersession transitions and records each change in `audit_events`. It applies source authority only where the original guideline specifies it; unresolved document-type authority remains blocked for later workflow configuration. Approval, Process Owner date proposal, KMT date confirmation, and release are separate audit events. A status string alone cannot authorize an action.

Constraint proposal: unique (`document_id`, `sequence_no`). Do not constrain `revision_label` to be unique. `sequence_no` is an internal row order, not ONP's official revision-number scheme.

### `document_files`

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `document_version_id` | unsigned bigint | Required FK to `document_versions.id` |
| `original_filename` | varchar | User/source filename for display and traceability only |
| `storage_key` | varchar | Generated relative key in private storage; never a user-supplied path |
| `media_type` | varchar | Detected/validated media type |
| `byte_size` | unsigned bigint | Stored size in bytes |
| `sha256` | char(64) | Integrity hash of stored bytes |
| `uploaded_by_user_id` | unsigned bigint | FK to user who uploaded the file |
| `uploaded_at` | timestamp | Upload time |

A version can have multiple files. `storage_key` is unique. Store files on the private local disk outside the public web root; accept PDF, DOCX, XLSX, JPEG, and PNG up to 50 MiB each; do not provide inline previews. Store SHA-256 hashes and force downloads. New versions receive new files; there is no file overwrite or delete operation. File roles/rendition names are not added until a requirement defines them.

### `audit_events` (document lifecycle and audit evidence)

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `target_type` | varchar | Validated entity name, such as document, version, type, category, classification, or file |
| `target_id` | unsigned bigint | ID of the affected Phase 3 entity |
| `document_id` | unsigned bigint, nullable | FK to `documents.id` for document/version/file history; null for configuration changes |
| `actor_user_id` | unsigned bigint, nullable | FK to user; null reserved for documented system actions |
| `event_type` | varchar | Explicit event name, validated by domain logic; approval, effective, release, supersession, and withdrawal are distinct events |
| `from_state` | varchar, nullable | Prior document/version state when applicable |
| `to_state` | varchar, nullable | New document/version state when applicable |
| `occurred_at` | timestamp | Event time |
| `reason` | text, nullable | Required by domain rules for events such as withdrawal |
| `metadata` | json, nullable | Limited non-sensitive event context; never file bytes, passwords, tokens, or arbitrary permission lists |

Each document-state transition creates an event in the same database transaction as the state update. Document creation, code assignment, file/version creation, review decisions, approval, Process Owner date proposal, KMT date confirmation, release, and supersession use separate events and timestamps rather than one combined status/date. Type and category configuration changes are also recorded. The event table is append-only through application authorization; ordinary users receive no edit/delete operation. Future modules may require a broader audit design.

Suggested indexes: (`target_type`, `target_id`, `occurred_at`), (`document_id`, `occurred_at`), and (`actor_user_id`, `occurred_at`). Because a generic target reference cannot have a normal foreign key to several different tables, the app must validate the target and referenced Phase 3 records must be deactivated rather than physically deleted. The `actor_user_id` FK and optional `document_id` FK retain database-level integrity where practical.

## Relationships and cardinalities

- One document type has zero or more documents; each document has one type.
- One category may have zero or more child categories and zero or more documents; each document has zero or one category.
- One classification may be used by zero or more documents; a document has zero or one classification while draft/incomplete.
- One document has zero or more versions; each version belongs to one document.
- One version has zero or more files; each file belongs to one version.
- One document has zero or more audit events; an event may refer to its document timeline or to a version/file through its typed target reference.
- User and organizational-unit relationships depend on the actual Phase 2 tables and their approved scope model.

## Open decisions and items deferred

- **D-019:** Use `ONP-[SER]-[SEC]-[DOC_TYPE]-[SEQ]`, with `-[UNI]` for unit-level documents. Keep the Master Code Register separate from the Document Control Register.
- **D-020:** Use Major.Minor revisions starting at `1.0`; increment minor revisions by `.1`, and increment major revisions while resetting minor to `.0`.
- **D-021:** Use official source values only and enforce global uniqueness. Preserve repeated/conflicting source rows, mark all affected values conflicted, and block them from new assignments until corrected by ONP.
- **D-022:** Do not model paper copy issuance, retrieval, stamps, or shredding here. Physical/digital control remains open and belongs with the approved Phase 5 distribution design.
- **D-023:** Do not auto-correct source fields or resolve ONP annex/form-code discrepancies. Migration must later preserve source values and flag conflicts.
- **D-009:** Authenticated actor/time evidence is not a claim of legally compliant electronic signature.
- Approval, release, distribution, retention, migration, and workflow execution tables are outside this Phase 3 initial schema draft except for the document event reference needed to preserve lifecycle history.

## Migration order if this draft is approved

1. Confirm the Phase 2 user and organizational-unit tables and their foreign-key behavior.
2. Create document type, category, and classification lookups.
3. Create document identities and document versions.
4. Create private file metadata and audit event tables.
5. Add indexes and constraints only after the owner resolves the relevant code/revision questions.

Migrations for Tasks 3.2 to 3.5 have been generated from this approved schema and approved D-010 and D-019 to D-021 decisions. They have not been run. D-017 remains open for document types and approval stages whose authority is not explicit in the ONP source.

## Phase 5 distribution schema extension (draft for owner review)

**Status: Draft for Task 5.1 review (2026-11-16). No Phase 5 migration is authorized by this draft.**

This proposal covers digital distribution records for an exact released document version and recipient acknowledgment tracking. It does not settle physical-copy controls in D-022 or legal/electronic-signature requirements in D-009.

### Design boundaries

- A distribution references one exact `document_versions` row. The referenced version must be released when the distribution is issued. A later release or revision creates a separate distribution; it does not change this record.
- Unit, role, and individual targets are supported by FR-09.2. Group targets are resolved to active individual accounts at issue time so the recipient set is preserved as evidence.
- Each resolved recipient has an acknowledgment requirement and optional due date. An overdue status is derived from a pending required acknowledgment and its due date using the institution's configured local date; no fixed due interval is assumed.
- Acknowledgment is separate from training or competency evidence (FR-09.5). No training evidence table or capture behavior is proposed until the owner defines and approves its purpose, fields, and authority.
- This design has no paper copy numbers, physical signatures, stamps, printouts, retrieval, shredding, or emergency/provisional issuance. Those controls remain open under D-022.
- Authenticated actor and time evidence is not a digital signature claim (D-009).

### Proposed tables

#### `document_distributions`

One issue event for one released version.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `document_version_id` | unsigned bigint | Required FK to `document_versions.id`; restrict deletion |
| `issued_by_user_id` | unsigned bigint | Required FK to `users.id`; proposed source-aligned issuer is an authorized KMT user |
| `issued_at` | timestamp | Time the exact version was issued to the resolved recipients |
| `created_at`, `updated_at` | timestamps | Record timestamps; distribution content should be immutable after issue |

#### `distribution_targets`

The intended scope supplied for a distribution. One target row identifies exactly one organizational unit, role, or individual user through nullable foreign keys and a required `target_type`. A database check constraint should require the matching foreign-key column and reject the other two. All targets belong to one `document_distributions` row. Store a `target_label_snapshot` for historical display. Preserve target definitions after issue; do not re-resolve them when role assignments change.

Proposed columns: `id`, `document_distribution_id`, `target_type`, nullable `organizational_unit_id`, nullable `role_id`, nullable `user_id`, `target_label_snapshot`, and timestamps. Foreign keys restrict deletion. Use separate unique constraints on (`document_distribution_id`, `organizational_unit_id`), (`document_distribution_id`, `role_id`), and (`document_distribution_id`, `user_id`) to prevent duplicate targets of each type. A single composite unique constraint is insufficient with nullable target columns because MySQL permits repeated null values in unique indexes.

#### `distribution_recipients`

One recipient snapshot per individual account for a distribution, whether reached through a unit, role, or individual target.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `document_distribution_id` | unsigned bigint | Required FK to distribution; restrict deletion |
| `user_id` | unsigned bigint | Required FK to recipient account; restrict deletion |
| `recipient_name_at_issue` | varchar | Snapshot display name for the issued recipient list |
| `acknowledgment_required` | boolean | Snapshotted requirement for this recipient |
| `due_at` | date, nullable | Optional owner-approved deadline in the institution's local calendar; null means no overdue calculation |
| `acknowledgment_status` | varchar | `not_required`, `pending`, `acknowledged`, or `exempted`; overdue is derived for pending required acknowledgments |
| `acknowledged_by_user_id` | unsigned bigint, nullable | FK to user recording the acknowledgment; proxy authority remains an open decision |
| `acknowledged_at` | timestamp, nullable | Acknowledgment time |
| `exempted_by_user_id` | unsigned bigint, nullable | FK to user recording an approved exemption |
| `exempted_at` | timestamp, nullable | Exemption time |
| `exemption_reason` | text, nullable | Required when an exemption is recorded |
| `created_at`, `updated_at` | timestamps | Recipient record timestamps |

Unique constraint: (`document_distribution_id`, `user_id`) so overlapping targets create one acknowledgment state per person.

#### `distribution_recipient_sources`

Preserves every target that resolved to a recipient, including when the same user is reached through multiple targets. Columns: `distribution_recipient_id` FK, `distribution_target_id` FK, and `created_at`; composite primary key on the two foreign keys. Restrict deletion of linked evidence.

### State and audit rules

- Issue a distribution only for a version whose status is `released`; check again inside the issuing transaction.
- Resolve eligible active users from the selected user, role, or organizational-unit target at issue time. A user reached through multiple targets receives one recipient row linked to each source target.
- `not_required` recipients do not receive an acknowledgment action. `pending` transitions to `acknowledged` by the named recipient or to `exempted` by an active KMT distribution assignee. Exemption applies to one recipient row and requires a reason. Acknowledgment and exemption actors/times are retained; corrections are subsequent audit events.
- Derive `overdue` when acknowledgment is required, status is pending, a due date exists, and the due date is earlier than the institution-local date. No background job is required for status correctness; notifications remain Phase 7.
- Proposed audit events include `distribution.issued`, `distribution.acknowledged`, and `distribution.exempted`, each linked to the document. Audit records are append-only. Updates to a recipient's current acknowledgment state must be transactionally paired with audit evidence.
- Distribution issue, acknowledgment, and exemption authority must be enforced server-side. System Administrator status alone grants no distribution or acknowledgment authority.

### Authority mapping and open points for approval

- **Verified source fact:** KMT distributes documents and maintains document-control records; Process Owners keep their unit lists of authorized copies; Authorized Persons safeguard copies in their unit. The source does not specify digital target resolution.
- **Recommendation:** allow only a user with the source-based KMT distribution permission to issue a digital distribution; let each resolved recipient acknowledge their own receipt. Keep any proxy acknowledgment unavailable unless explicitly approved.
- **Owner decision D-024:** KMT may exempt one recipient with a reason. Bulk target exemptions are unavailable.
- **Open decision:** whether acknowledgment is required by default or selected per distribution/recipient; this draft stores a snapshotted per-recipient value.
- **Open decision:** whether training evidence should be recorded and, if so, its minimum fields, source, recorder authority, and relationship to a particular exact version. Until decided, training capture remains unavailable.
- **Open decision D-022:** determine physical copy, stamp, retrieval, shredding, monthly printout, and provisional issuance controls separately. This schema creates no digital substitute for those paper procedures.
- **Open decision D-009:** authenticated acknowledgment is a recorded receipt action, not a legally compliant electronic signature.

### Relationships and indexes (proposed)

- One released document version has zero or more distributions; each distribution identifies exactly one version.
- One distribution has one or more target specifications and one or more resolved recipient snapshots.
- One recipient can have multiple target-source links within a distribution, but one acknowledgment state for that distribution.
- Index distributions by (`document_version_id`, `issued_at`), recipient queues by (`user_id`, `acknowledgment_status`, `due_at`), and target source rows by target foreign key.
- Restrict deletion of distributions, targets, recipients, and source links referenced by audit evidence; retire or retain records instead of deleting them.

Owner approval (2026-11-16): proceed with the digital distribution schema in Phase 5. Training evidence remains excluded until separately defined. Owner decision D-024 authorizes active KMT assignees to exempt one recipient with a required reason; bulk target exemptions are unavailable. D-022 physical-copy controls remain open. Migrations and other implementation artifacts are tracked by the Phase 5 plan tasks.

## Phase 6 migration schema extension

**Status: Phase 6 schema draft, generated under the owner-approved Phase 6 scope (2026-11-16); migration generated but not run.**

`document_migration_batches` records a named import batch, a source description, creator, open/closed status, and closure time. `document_migration_items` stores each source file under a generated private storage key with original filename, detected media type, byte size, SHA-256, source location, source-provided metadata, normalized/mapped metadata, duplicate candidates, verification state/notes/actor/time, and an optional link to a later registered document. Source values and normalized mappings remain separate. Matching hashes/codes/titles create review candidates only; they never overwrite an existing document. Upload does not create approval or release evidence. Reconciliation counts are derived from item statuses. Retain migration evidence; rollback is blocked once a batch exists. Supported file types and size follow D-010.

Only the existing KMT `document.register` permission may manage batches and verify migration items. System Administrator status alone grants no authority. Imported items remain staging evidence; registration must be a distinct action and cannot fabricate approval dates, approvers, ownership, or revision history.

## Phase 7 extended-control schema draft

**Status: Draft generated under the owner-approved Phase 7 scope (2026-11-16); migration generated but not run.**

The extension adds an external-reference registry and document link table; configurable retention schedules with nullable, inactive periods until D-011 is resolved; operational-record metadata; legal-hold evidence; approval/evidence fields for disposition requests; user-scoped in-app notifications; and backup-run evidence fields. It does not seed retention periods, create patient record fields, schedule backups, claim a backup succeeded without an evidence row, or automatically dispose of records. Backup destination and schedule remain under D-013; required reports and export formats remain under D-016. Under owner decision D-025, record-management/disposition actions use the KMT `records.manage` permission. Retention eligibility is blocked while no approved active schedule exists.

## Phase 4 workflow schema extension (draft for owner review)

**Status: Owner-approved for Phase 4 implementation (2026-11-12).** This section proposes workflow tables for Task 4.1. It does not resolve D-017. All lifecycle decisions must continue to follow the source roles and authority below. Do not configure or activate a workflow for an unsupported document type or stage.

### `workflow_templates`

Stable identity for a reusable workflow definition.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `template_key` | varchar | Stable unique identifier |
| `name` | varchar | Required display name |
| `description` | text, nullable | Purpose and use notes |
| `is_active` | boolean | Controls whether new versions may use a published template; does not affect existing instances |
| `created_by_user_id` | unsigned bigint | FK to user; template configuration is a system-administration action, never document approval authority |

### `workflow_template_versions`

Immutable published definitions; edits create a new version. Existing instances retain the exact version they started with.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `workflow_template_id` | unsigned bigint | FK to `workflow_templates.id` |
| `version_no` | unsigned integer | Unique within template; starts at 1 |
| `document_type_id` | unsigned bigint | FK to `document_types.id`; required so applicability is explicit |
| `category_id` | unsigned bigint, nullable | FK to `document_categories.id`; optional narrower match |
| `organizational_unit_id` | unsigned bigint, nullable | FK to `organizational_units.id`; null means institution scope |
| `trigger_event` | varchar | Initially `version.submitted`; validated by application logic |
| `status` | varchar | Draft, published, or retired; transitions validated by application logic |
| `published_by_user_id` | unsigned bigint, nullable | FK to user recording publication of configuration, not approval of any document |
| `published_at` | timestamp, nullable | Publication time |

Suggested unique key: (`workflow_template_id`, `version_no`). Prevent overlapping published definitions for the same applicability and trigger. Template publication does not grant document review, approval, or release authority.

### `workflow_template_stages`

Ordered steps within one immutable template version.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `workflow_template_version_id` | unsigned bigint | FK to `workflow_template_versions.id` |
| `stage_key` | varchar | Stable identifier unique within template version |
| `name` | varchar | Required display name |
| `purpose` | text, nullable | What the source or approved process requires at this step |
| `stage_order` | unsigned integer | Sequential order; stages sharing a group may be represented as parallel only when that pattern is approved |
| `parallel_group` | unsigned integer, nullable | Groups stages eligible to run concurrently; no ONP parallel workflow is implied by this schema |
| `action_type` | varchar | Review, recommendation, approval, or release; validated against source authority and phase boundaries |
| `is_mandatory` | boolean | Whether completion is required to proceed |
| `required_decisions` | unsigned small integer | Quorum count; values above 1 remain unavailable until owner resolves D-017 |
| `created_at` / `updated_at` | timestamps | Record timestamps |

### `workflow_stage_eligible_roles`

Join table restricting each stage to configured, existing ONP roles; it is not a custom permission matrix.

| Column | Proposed type | Notes |
|---|---|---|
| `workflow_template_stage_id` | unsigned bigint | FK to `workflow_template_stages.id` |
| `role_id` | unsigned bigint | FK to `roles.id`; application validates the source-defined role and its permission |
| `scope_type` | varchar | Institution or the document's owning unit; application enforces exact assignment scope |

Primary key: (`workflow_template_stage_id`, `role_id`). No arbitrary action or permission strings are stored here.

### `workflow_instances`

One execution for a specific submitted document version and the exact published definition used.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `document_version_id` | unsigned bigint | Required FK to `document_versions.id`; unique to prevent duplicate active instances for one version |
| `workflow_template_version_id` | unsigned bigint | Required FK to the exact `workflow_template_versions.id` used |
| `status` | varchar | Pending, in progress, returned, completed, or cancelled; supported transitions validated by application logic |
| `started_by_user_id` | unsigned bigint | FK to submitting user |
| `started_at` | timestamp | Start time |
| `completed_at` | timestamp, nullable | Completion time |

### `workflow_stage_instances`

Runtime state for each stage and each pass through the workflow after return/resubmission.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `workflow_instance_id` | unsigned bigint | FK to `workflow_instances.id` |
| `workflow_template_stage_id` | unsigned bigint | FK to the immutable stage definition |
| `pass_no` | unsigned integer | Starts at 1; increments on resubmission without rewriting prior decisions |
| `status` | varchar | Pending, active, completed, returned, skipped, or cancelled |
| `started_at` | timestamp, nullable | Stage activation time |
| `completed_at` | timestamp, nullable | Stage completion time |

Suggested unique key: (`workflow_instance_id`, `workflow_template_stage_id`, `pass_no`).

### `workflow_tasks`

Role/scope queue or named assignment for one stage pass.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `workflow_stage_instance_id` | unsigned bigint | FK to `workflow_stage_instances.id` |
| `role_id` | unsigned bigint | FK to `roles.id`; role is resolved from the approved stage definition |
| `organizational_unit_id` | unsigned bigint, nullable | FK to `organizational_units.id`; exact scope for unit roles, null for institution roles |
| `assigned_user_id` | unsigned bigint, nullable | FK to `users.id`; null means an authorized role member may claim/act on the scoped task |
| `status` | varchar | Pending, claimed, completed, or cancelled |
| `assigned_at` | timestamp | Assignment/queue creation time |
| `due_at` | timestamp, nullable | Only used if a service target is approved and configured |

### `workflow_decisions`

Append-only decision evidence attached to an assigned task.

| Column | Proposed type | Notes |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `workflow_task_id` | unsigned bigint | Unique FK to `workflow_tasks.id`; one decision per task assignment |
| `actor_user_id` | unsigned bigint | FK to `users.id`; must hold the required active role/scope when acting |
| `decision_type` | varchar | Reviewed, recommended, approved, rejected, or returned-for-revision; must match the stage's authorized action |
| `comment` | text, nullable | Decision explanation; return/rejection requires a reason |
| `decided_at` | timestamp | Decision time |

Decisions cannot be edited or deleted through the application. A correction is a subsequent event/assignment. Authenticated actor and time are evidence of a system action, not a legal or digital signature (D-009).

### Authority mapping and constraints

| Source role/process | Workflow use supported by the source | Constraint |
|---|---|---|
| Process Owner | Originates and revises content; proposes effective date after approval | May submit for review within assigned unit; cannot gain final approval from that role |
| QMR | Reviews/verifies documentation before final approval | Review stage only where source applies; does not become final approver |
| Department Head, then Service Chief | Reviewers shown on policy and specific-guideline templates | Keep the specified order; exact template applicability remains subject to D-017 |
| MCC | Final approval of Master Copies of manuals, SOPs, and guidelines | Final approval is not release; no extension to other document types |
| KMT | Code authority; registers, stamps, distributes, and controls documents; confirms proposed effective date | Registration/release actions remain distinct from MCC approval |
| Forms Committee | Approves internal forms before KMT coding | Internal/external form routing is unresolved; do not instantiate until classified and approved under D-017 |
| Quality Management Committee | Approves proposed codes for high-risk documents | This is code approval, not document final approval; exact coding handoff remains subject to source interpretation |

Do not create or publish a workflow for document types where the final approver, sequence, quorum, or form routing is not specified. The source describes a high-level sequence (Originate → Review/Code → Approve → Stamp/Register → Distribute) but does not define every transition. Workflow cancellation, delegation, quorum greater than one, deadlines, escalation, and use of parallel stages remain unavailable until source authority or a separate owner decision resolves them. Template configuration cannot assign roles or actions beyond the existing approved ONP role/permission set. The System Administrator can maintain configuration but does not gain document approval authority.

### Relationships and indexes (proposed)

- A template has many immutable versions; a version has ordered stages and eligible ONP roles.
- A workflow instance belongs to one document version and one exact template version.
- A workflow instance has stage instances; a return/resubmission creates new pass rows rather than overwriting previous decisions.
- A stage instance has role/scope tasks; each task has at most one immutable decision.
- Add indexes for template applicability/status, (`workflow_instance_id`, status), (`workflow_stage_instance_id`, status), (`assigned_user_id`, status), and (`actor_user_id`, `decided_at`).
- Restrict deletion of definitions referenced by versions/instances/tasks/decisions. Retire/deactivate instead.
- Extend `audit_events.target_type` validation to workflow entities and retain the owning `document_id` for each workflow event.

The Phase 4 template-configuration migration (`2026_11_12_000010`) has been generated for Task 4.2 but has not been run. It creates template identities, immutable versions, stages, and eligible-role links only; workflow execution tables are deferred to Task 4.3. Any outstanding D-017 authority remains a publication/runtime block.
