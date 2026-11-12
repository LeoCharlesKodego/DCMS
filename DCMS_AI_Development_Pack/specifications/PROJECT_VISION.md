# Project Vision and Scope

## 1. Product identity

**Name:** Institutional Document Control Management System (DCMS)

A configurable, institution-independent platform for controlling institutional documents and related operational records, initially designed around healthcare institutions.

## 2. Product vision

Provide one centralized system through which an institution can configure its organizational structure, users, document classifications, numbering rules, approval authorities, document lifecycle, distribution, retention, and audit evidence.

The same application should be reusable by different institutions without hard-coding one institution's organizational chart or approval process.

## 3. Deployment context

- Primary deployment: local institutional network (client-server).
- Core functions must operate without internet access.
- Users connect from authorized computers through a local network browser.
- Central server is the source of truth for database and controlled files.
- Optional internet-dependent functions must not block core document-control operations.

## 4. Onboarding scenarios

The system must support:
1. **New institution:** configure the system and establish documentation.
2. **Existing institution:** migrate and verify existing documentation while preserving established conventions.
3. **Partially documented institution:** inventory existing documents, identify gaps, and manage remediation.

Do not force an existing institution to adopt new document codes or rewrite its QMS documents merely to fit the software.

## 5. In-scope document domains

- Quality manuals, policies, objectives, and scope documents.
- Procedures, SOPs, work instructions, protocols, and guidelines.
- Forms, templates, registers, and controlled lists.
- Clinical and operational procedures, subject to appropriate technical/clinical review.
- External references such as standards, laws, regulations, and official guidance.
- Operational evidence records such as training, audits, inspections, calibration, and maintenance records, where approved by the institution.

## 6. Out of scope

DCMS is not an EMR, hospital information system, laboratory information system, pharmacy system, or patient charting application.

Do not design ordinary DCMS modules to store patient medical histories, prescriptions, patient laboratory results, patient charts, or patient treatment records. Patient information is sensitive and requires a separate purpose-built system and controls.

## 7. Core product principles

- Configurable rather than institution-specific.
- Offline-first for core functions.
- Centralized and traceable document control.
- No automatic approval.
- No silent replacement of released documents.
- Preserve document history and source evidence.
- Least-privilege access and separation of duties.
- Explicit approval authority and effective-date management.
- Verifiable backups and restoration.
- Evidence-based development and testing.

## 8. Success criteria

The product should allow an authorized institution administrator to:
- configure an institution and its organizational hierarchy;
- define document types, categories, codes, and revision rules;
- configure workflow templates and authority assignments;
- register, revise, review, approve, release, distribute, and archive documents;
- import and verify existing documents;
- track acknowledgment and training evidence;
- manage operational records and retention schedules;
- retrieve audit history and reports;
- operate core functions on a local network without internet.
