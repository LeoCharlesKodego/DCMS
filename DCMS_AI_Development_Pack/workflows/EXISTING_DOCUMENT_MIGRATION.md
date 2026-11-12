# Existing Document Migration Workflow

## Objective

Bring an institution's existing controlled documents into DCMS without losing source identity, revision history, approval evidence, or institutional conventions.

## Migration stages

1. Create migration batch and record source.
2. Upload files in supported formats.
3. Capture original filename, file size, hash, upload actor, and source path/description.
4. Map source metadata to DCMS fields while retaining original values.
5. Detect possible duplicate codes, titles, and file hashes.
6. Check original approval and effective-date evidence.
7. Assign verification status.
8. Route uncertain or conflicting records to authorized review.
9. Register verified items without inventing missing approval information.
10. Preserve source file and available historical versions.
11. Generate a batch report with imported, verified, pending, rejected, duplicate, and issue counts.

## Required preservation fields

Where supplied by the source:
- original document code;
- original title;
- document type/category;
- original revision/version label;
- original approval authority;
- approval date;
- effective date;
- owner and department;
- original filename and file format;
- source location or source system;
- historical version relationship;
- source file hash.

## Verification statuses

- pending_verification
- verified
- requires_correction
- duplicate_candidate
- conflicting_information
- obsolete
- superseded
- rejected_for_migration

## Critical safeguards

- Import is not approval.
- Do not generate fabricated approval dates, approvers, revision labels, or owners.
- Do not overwrite a current document because an imported file has a matching code.
- Preserve original values separately from normalized DCMS values.
- Require authorized resolution of conflicts.
- Produce a migration audit trail and final reconciliation report.
