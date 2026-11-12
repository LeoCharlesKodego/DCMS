# Conceptual Domain Model

This is a conceptual model, not a finalized physical database schema. Detailed cardinalities, column types, constraints, and indexes must be approved before migrations are generated.

## Institution and organization
- Institution
- InstitutionSetting
- OrganizationalUnit (self-referencing hierarchy)
- User
- Role
- Permission
- UserRoleAssignment (role + organizational scope + validity)

## Document identity and classification
- Document
- DocumentType
- DocumentCategory (hierarchical)
- DocumentOwnerAssignment
- DocumentRelationship
- DocumentAccessPolicy

A Document is the stable identity/registry entry. It is not the file itself and is not a particular revision.

## Version and file
- DocumentVersion
- DocumentFile
- VersionEvent

A Document has many DocumentVersions. A DocumentVersion may have multiple DocumentFiles. Each file should have metadata, storage location, media type, size, hash, uploader, and upload timestamp.

## Workflow
- WorkflowTemplate
- WorkflowTemplateVersion
- WorkflowStep
- WorkflowInstance
- WorkflowInstanceStep or ApprovalTask
- ApprovalDecision
- ApprovalDelegation

A WorkflowInstance is created for a specific document version and retains the exact template version used. Workflow template changes must not rewrite historical or active instances.

## Release and distribution
- DocumentRelease
- Distribution
- DistributionRecipient
- Acknowledgment
- TrainingRecord

Release should identify the exact version and effective date. Distribution references a release/version, not just the document identity.

## External references and records
- ExternalReference
- DocumentExternalReference
- RecordType
- RetentionSchedule
- ControlledRecord
- RecordFile
- LegalHold
- DispositionRequest
- DispositionDecision

Patient clinical records are not ordinary ControlledRecords in this DCMS design.

## Migration
- MigrationBatch
- MigrationItem
- MigrationFile
- MigrationIssue
- MigrationVerification

MigrationItem should preserve original metadata separately from normalized DCMS fields, so source facts are not lost during mapping.

## Audit and operations
- AuditEvent
- Notification
- NotificationRule
- BackupJob
- BackupVerification
- SystemEvent

## Important modeling cautions

- Do not put all approvals into one mutable field on Document.
- Do not store version history as comma-separated values.
- Do not store permission lists as arbitrary JSON if they need relational enforcement and querying.
- Do not assume one workflow step equals one person; steps may support role-based assignment, parallel reviewers, quorum, or sequential approval depending on approved requirements.
- Avoid deleting referenced historical entities; use controlled deactivation/archiving.
