# Open Decisions Register

The AI must not silently guess these decisions. Record each answer, date, and approval status here.

| ID | Decision | Current status | Notes |
|---|---|---|---|
| D-001 | Exact healthcare facility type | Approved | Ospital ng Palawan is the first and only institution for this installation. |
| D-002 | Institution size and expected departments/users | Partially approved | Planning estimate: approximately 100 users. Organizational-unit count remains open. |
| D-003 | Approval governance model | Partially approved | Owner directs the project to follow only the original ONP guidelines. D-029 records a limited owner-approved flow amendment for manuals, procedures (SOPs), and ordinary guidelines explicitly selected through a per-type route setting; other conflicts and exact chains needing interpretation remain tracked in D-017 and the source issues list. |
| D-004 | Can institutions fully customize workflows? | Approved in principle | Yes: reviewers, sequence, parallel stages, and final authority should be configurable |
| D-005 | One institution per installation or multi-tenant platform? | Approved | One installation for one institution: Ospital ng Palawan only. |
| D-006 | Final technology stack | Approved | Laravel/PHP backend; React with TypeScript and Vite; Tailwind; authenticated REST/JSON API; MySQL database. See decision record below. |
| D-007 | Server operating system and hardware | Open (deferred) | Owner wants to decide production server OS/hardware later. `governance/PHASE8_DEPLOYMENT_PROPOSAL.md` contains the reviewable planning baseline (Ubuntu Server 26.04.1 LTS; 8-core CPU, 32 GB ECC RAM, 2 x 1.92 TB SSD RAID 1, expandable) and network controls. No production target, procurement, or deployment is approved. |
| D-008 | Authentication and account recovery on offline LAN | Approved | Local DCMS accounts; separate administrator and user roles; administrators may manage system settings, user accounts, and user password resets. A second individual administrator can recover the primary admin; if both are locked out, use a logged, two-person server-side recovery procedure and require a new password. |
| D-009 | Electronic signature requirements | Open | Determine whether typed approval, authenticated action, or legally compliant digital signature is required |
| D-010 | Document file formats, size limits, and preview needs | Approved | PDF, DOCX, XLSX, JPEG, and PNG; 50 MiB per file; no inline preview |
| D-011 | Retention schedules and applicable legal bases | Open — proposal requested | Owner requested an ONP three-year proposal for review. Source evidence does not resolve trigger, digital/paper scope, or type-specific legal basis. See `RETENTION_SCHEDULE_PROPOSAL.md`. |
| D-012 | Mobile access requirement | Open | Desktop-first or responsive mobile use |
| D-013 | Backup schedule, destination, RPO, and RTO | Open — proposal prepared for review | `governance/PHASE8_DEPLOYMENT_PROPOSAL.md` proposes nightly encrypted full DB+private-file backups, 15-minute binlog archiving, separate ONP-managed primary and offline/independent secondary copies, 30-day daily / 12-month monthly retention, 15-minute DB RPO, 4-hour RTO, and monthly/quarterly restore exercises. No destination, schedule, target, or backup process is configured or approved. |
| D-014 | Applicable ISO edition and healthcare regulations | Open | Verify current authoritative editions and applicability before compliance mapping. Proposal baseline sections 15-16 also name ISO 10013, Philippine National Standards, DOH issuances, and the Data Privacy Act of 2012 (RA 10173) / NPC issuances; DPO and legal adviser to evaluate |
| D-015 | Existing documentation status and volume | Open | Does the institution already have ISO/QMS documents, and about how many? Drives migration scope and storage (Proposal section 20, Q4-Q5) |
| D-016 | Required reports and export formats | Partially approved | Owner approved JSON summary and audit reports for now; further report/export needs are deferred. |
| D-017 | Approval sequence and quorum rules | Open | Follow source assignments except for the limited D-029 amendment: manuals, procedures (SOPs), and ordinary guidelines explicitly selected through a per-type route setting use Process Owner → KMT verification → Division Head review (head of the division that owns the document) → MCC approval → Process Owner proposes the effective date → KMT confirms → KMT release, with QMR excluded. Policies and specific-guideline templates retain source-defined routing; internal/external forms and resolutions remain outside the new flow. D-017 remains open for form/resolution routing, quorum, and other unresolved authority details. Owner decision (2026-11-12): after MCC approval, the Process Owner proposes the effective date and KMT confirms it. |
| D-018 | Phase roadmap: pack Phases 0-8 or proposal Phases 0-10 | Approved | Use the pack's Phases 0-8 as the controlling sequence and include all proposal Phase 0-10 work by mapping it into those phases. See `reference/PLAN.md`. |
| D-019 | Document code format and component rules | Approved | Use the section 6 structure in the original coding guidelines, with the unit component for unit-level codes; use the official code list; keep the Master Code Register and Document Control Register separate. Conflicting source examples remain source evidence, not alternate rules. |
| D-020 | Revision numbering scheme | Approved | Use Major.Minor: start at 1.0; minor revisions increment by 0.1; a major revision increments the major number and resets the minor number to .0 (for example, 1.1 to 2.0). |
| D-021 | Code uniqueness scope and cleanup of duplicate or overlong codes | Approved | Enforce global uniqueness; preserve duplicate/conflicting official source rows and block all conflicted values from new assignments until ONP corrects the source list. |
| D-022 | Physical copy controls vs digital equivalents | Open | ONP uses blue/purple/red ink stamps, authorized copies, monthly hard-copy printouts, shredding, and emergency/ICU protocols used before coding. Decide what becomes digital, what stays on paper, and the provisional-issuance rule |
| D-023 | Corrections to the source documents | Open | Title size 12 vs 14 is resolved by D-030 (14 pt default, KMT-editable with version history). Annex numbering mismatch, DRRF form code mismatch, and GUID-002 header title remain open. The system never auto-corrects migrated documents. |
| D-024 | Acknowledgment exemption authority and scope | Approved | KMT may exempt one recipient with a reason; no bulk target exemption. |
| D-025 | Operational records and disposition authority | Approved | Owner decision (2026-11-16): KMT manages operational records and approves disposition. Retention periods remain open under D-011. |
| D-026 | ONP three-year retention proposal | Open — revised proposal | Owner directed the proposal remain inactive and be revised; no trigger is proposed. See `RETENTION_SCHEDULE_PROPOSAL.md`. |
| D-027 | Backup schedule and destination timing | Approved | Defer production backup schedule and destination to Phase 8 deployment planning; D-013 remains open. |
| D-028 | Phase 7 reporting baseline | Approved | Provide JSON summary and audit reports for now; additional reports and exports are deferred. |
| D-029 | Type-specific approval flow and same-account KMT verification/release | Approved | For manuals, procedures (SOPs), and ordinary guidelines selected through the explicit per-document-type route setting only: Process Owner → KMT verification → head of the division that owns the document (scoped Division Head review) → MCC approval → Process Owner proposes the effective date → KMT confirms → KMT release; QMR excluded. Policies and specific-guideline templates retain source-defined routing; internal/external forms and resolutions remain outside this flow pending D-017. Same-account KMT verify/release defaults off and may be enabled by setting; separate audit events and setting-change actor/time are required. Amendments 2026-11-16: owner confirmed the Division Head resolution and effective-date proposal/confirmation steps; owner selected an explicit per-document-type route profile to distinguish ordinary guidelines from specific-guideline templates. |
| D-030 | Default title formatting setting | Approved | 14 pt title default, editable by KMT with setting version history; resolves the title-size part of D-023. |
| D-031 | Submission validation and code extraction behavior | Approved | Retain D-010 formats; extraction is local/offline with no OCR in Phase 4B. Extract code from PDF text layers, DOCX text, or XLSX cells; JPEG/PNG and scanned PDFs always require KMT manual code entry. Only KMT enters or assigns codes. Submitters update the document to match the KMT-assigned code and resubmit; they never choose or enter a code. Formatting checks that cannot be evaluated must say “not checked, KMT to verify manually”, never “pass”. Amendment 2026-11-16: owner confirmed no OCR; PDFs without extractable text are treated as scanned, and code entry/assignment is KMT-only. Submitters never choose or enter codes; only KMT assigns them, and submitters update files to match the assigned code before resubmission. |

## Decision recording template

- Decision ID:
- Question:
- Options considered:
- Approved decision:
- Reason:
- Impacted files/modules:
- Approved by:
- Date:
- Status: proposed / approved / superseded

## Recorded decisions

### D-029 — Type-specific approval flow and same-account KMT verification/release

- Decision ID: D-029
- Question: What approval sequence applies to manuals, procedures (SOPs), and guidelines, and may one KMT account verify and release the same document?
- Options considered: Preserve the source-defined routing for these types or adopt the proposed type-specific sequence; require separate KMT accounts or permit same-account verification/release under a setting.
- Approved decision: For manuals, procedures (SOPs), and ordinary guidelines selected through the explicit per-document-type route setting only, use Process Owner → KMT verification → review by the head of the division that owns the document (permission scoped to that document and division) → MCC approval → Process Owner proposes the effective date → KMT confirms the date → KMT release; QMR is excluded from this flow. Policies and specific-guideline templates retain their existing source-defined review chain. Internal and external forms and resolutions are outside this flow until D-017 resolves their routing. Same-account KMT verification and release is disallowed by default. A setting may enable it; when enabled, verification and release create separate audit events. Changes to this setting are logged with the actor and timestamp.
- Reason: The project owner approved this limited workflow and separation rule on 2026-11-16.
- Impacted files/modules: `reference/PLAN.md` Phase 4B; workflow templates and instances; workflow decision and lifecycle authorization; KMT verification/release permissions; configuration audit; API and UI; automated tests.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

- Amendment (2026-11-16): The owner confirmed that Division Head review is assigned to the head of the division that owns the document, scoped to that document and division. After MCC approval, the Process Owner proposes the effective date and KMT confirms it before KMT release.

- Amendment (2026-11-16): The owner selected an explicit per-document-type route setting. Ordinary guidelines can use the D-029 chain while specific-guideline templates retain their source-defined chain.

### D-030 — Default title formatting setting

- Decision ID: D-030
- Question: What title size should be the default, and who may change it?
- Options considered: Retain the unresolved source difference or set an editable system default.
- Approved decision: Use 14 pt as the default title size. KMT may edit the setting; retain a version history of changes. This resolves the title-size part of D-023 only; other D-023 source issues remain open.
- Reason: The project owner approved this default and its edit/history controls on 2026-11-16.
- Impacted files/modules: `reference/PLAN.md` Phase 4B; formatting configuration and validation; KMT settings authorization and UI; audit/version history; automated tests.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

### D-031 — Submission validation and code extraction behavior

- Decision ID: D-031
- Question: How should submission code extraction, correction, validation, and scan handling work?
- Options considered: Reject unrecognized codes/files, allow submitters to update document content to match a KMT-assigned code with revalidation, or require KMT manual entry for scans.
- Approved decision: Retain D-010's accepted formats (PDF, DOCX, XLSX, JPEG, PNG), 50 MiB limit, and no-inline-preview setting. Extraction is local/offline and Phase 4B performs no OCR. Read the code from a PDF text layer, DOCX text, or XLSX cell text. JPEG/PNG files and scanned PDFs always require KMT manual code entry, recorded as manual. Only KMT enters or assigns codes. Submitters never choose or enter a code; they may update the document file to match a KMT-assigned code and resubmit, rerunning applicable checks for KMT verification. Automatic checks cover file type/readability, code format and approved-list validity, global uniqueness/conflicts, duplicates, and editable formatting rules. A formatting rule that cannot be evaluated must display “not checked, KMT to verify manually”, never pass. Do not silently alter source files.
- Reason: The project owner approved these validation and correction rules on 2026-11-16.
- Impacted files/modules: `reference/PLAN.md` Phase 4B; file intake and code extraction; validation services; KMT verification queue; submission UI/API; audit history; automated tests.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

- Amendment (2026-11-16): The owner confirmed extraction is local/offline with no OCR in Phase 4B. A PDF without extractable text is treated as scanned; scanned PDFs and JPEG/PNG always require KMT manual code entry. Formatting checks that cannot be evaluated must report “not checked, KMT to verify manually”, never “pass”.

- Amendment (2026-11-16): The owner confirmed that submitters never choose or enter codes. Only KMT enters or assigns codes; submitters may update the document to match the KMT-assigned code and resubmit.

### D-024 — Acknowledgment exemption authority and scope

- Decision ID: D-024
- Question: Who may exempt an acknowledgment, and at what scope?
- Options considered: KMT or recipient authority; one recipient or an entire target; require or omit a reason.
- Approved decision: An active KMT distribution assignee may exempt one recipient with a reason. An exemption applies only to that recipient's pending acknowledgment; bulk target exemptions are unavailable.
- Reason: The project owner directed that “KMT may exempt one recipient with a reason.”
- Impacted files/modules: Phase 5 distribution service, recipient status/audit, registry UI, API, tests, and `architecture/DATABASE_SCHEMA.md`.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

### D-025 — Operational records and disposition authority

- Decision ID: D-025
- Question: Which authority manages operational records and approves disposition requests?
- Options considered: KMT; RMIC/Records Officer with a separate approver; or configure roles and authorities first.
- Approved decision: KMT manages operational records and approves disposition. Applied retention periods remain unconfigured until D-011 is decided. Disposition records retain request, decision, and execution evidence; no automatic deletion occurs.
- Reason: The project owner directed that “KMT manages and approves disposition.”
- Impacted files/modules: Phase 7 record-management permissions, retention schedules, operational records, holds, disposition workflow, audit, and UI.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

### D-026 — ONP three-year retention proposal

- Decision ID: D-026
- Question: What retention schedule should be prepared for owner review from current ONP source evidence?
- Options considered: Apply one schedule to all records; use the ONP three-year wording only for its stated class; defer all schedule drafting.
- Approved decision: Prepare an inactive proposal for Master and Authorized controlled-document copies using the source's “3 years or until rescinded/revised” wording. Do not generalize to operational records. On 2026-11-16 the owner directed that the proposal remain inactive and be revised; no trigger is proposed. Digital/paper scope remains unresolved under D-022.
- Reason: The owner requested “Prepare ONP three-year proposal for review,” then directed “Keep inactive; revise the proposal.”
- Impacted files/modules: `governance/RETENTION_SCHEDULE_PROPOSAL.md`; retention configuration and Phase 7 disposition controls.
- Approved by: Project owner (proposal request)
- Date: 2026-11-16
- Status: Proposed; revised per owner direction

### D-027 — Backup schedule and destination deferral

- Decision ID: D-027
- Question: When should the production backup schedule and destination be selected?
- Options considered: Select during Phase 7 or defer to deployment planning.
- Approved decision: Defer schedule and destination to Phase 8 deployment planning. D-013 remains open for destination, cadence, RPO/RTO, and restore requirements.
- Reason: The owner directed “Defer schedule and destination to deployment.”
- Impacted files/modules: Backup configuration, monitoring, deployment and recovery documentation.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

### D-028 — Phase 7 reporting baseline

- Decision ID: D-028
- Question: Which initial Phase 7 reports and formats should be delivered now?
- Options considered: Select additional named reports and export formats or retain the current JSON summary/audit baseline.
- Approved decision: Keep the JSON summary and audit reports for now; defer additional report and export requirements under D-016.
- Reason: The owner directed “Keep JSON summary and audit reports for now.”
- Impacted files/modules: Reports API, Reports screen, and `reference/PLAN.md`.
- Approved by: Project owner
- Date: 2026-11-16
- Status: Approved

### D-006 — Final technology stack

- Decision ID: D-006
- Question: Which backend, frontend, API, and database stack should DCMS use?
- Options considered: Candidate stack in `architecture/TECHNICAL_DECISIONS.md` and the additional TypeScript, Vite, and REST/JSON details in `specifications/PROPOSAL_BASELINE.md` section 14; MySQL or MariaDB.
- Approved decision: Laravel/PHP backend; React with TypeScript and Vite; Tailwind; authenticated REST/JSON API; MySQL database.
- Reason: The owner approved the candidate stack and selected MySQL.
- Impacted files/modules: `architecture/TECHNICAL_DECISIONS.md`; frontend; backend/API; database integration.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-007 — Server operating system and hardware

- Decision ID: D-007
- Question: Which operating system and server hardware should host the local DCMS installation?
- Options considered: Owner has not specified existing server hardware or an operating system. Recommendation: Ubuntu Server 26.04.1 LTS on a physical server with an 8-core CPU, 32 GB ECC RAM, two 1.92 TB enterprise SSDs in RAID 1, and room to expand memory and storage.
- Approved decision: No final production server selection. The owner approved using this recommendation only as a temporary planning assumption and will decide the production server later. It assumes the institution can administer Linux and that the eventual document volume fits the mirrored storage capacity.
- Reason: Ubuntu 26.04.1 is the current Ubuntu LTS release and Canonical lists five years of standard security and maintenance updates, through May 2031. The hardware is a preliminary sizing estimate for about 100 users; expected concurrency and document volume are not yet known.
- Impacted files/modules: Local deployment, server operations, database and document-file hosting, backup planning.
- Approved by: Project owner (temporary assumption only)
- Date: 2026-11-12
- Status: proposed; final deployment decision deferred

### D-001 — First institution

- Decision ID: D-001
- Question: Which institution and facility type will this installation serve first?
- Options considered: The owner selected Ospital ng Palawan (ONP), a hospital identified by the source guidelines.
- Approved decision: ONP is the first and only institution for this installation.
- Reason: The owner confirmed “yes” to ONP as first institution and specified “this only for ONP.”
- Impacted files/modules: Institution configuration, deployment/tenancy assumptions, ONP reference data.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-002 — Initial size estimate

- Decision ID: D-002
- Question: What initial user and organizational-unit scale should be planned for?
- Options considered: Owner estimate; exact organizational-unit count not provided.
- Approved decision: Plan around approximately 100 users. Organizational-unit count remains open.
- Reason: The owner estimated “more or less 100.”
- Impacted files/modules: Capacity planning and initial configuration.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Partially approved

### D-003 — Approval governance source

- Decision ID: D-003
- Question: Which source governs ONP approval roles and authority?
- Options considered: Original ONP guidelines or a newly defined authority model.
- Approved decision: Follow only the original ONP guidelines. Do not introduce an AI-defined approval model. Conflicting or underspecified chains remain unresolved and are tracked under D-017 and the source issues in `reference/ONP_ORIGINAL_GUIDELINES.md`.
- Reason: The owner said, “we follow only the original guidelines.”
- Impacted files/modules: Approval governance, workflow templates, review and release configuration.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Partially approved

### D-005 — Installation tenancy

- Decision ID: D-005
- Question: Should an installation serve one institution or multiple institutions?
- Options considered: Single institution per installation or multi-tenant installation.
- Approved decision: One installation serves one institution: ONP only.
- Reason: The owner specified “one installation, one institution” and “this only for ONP.”
- Impacted files/modules: Deployment and institution configuration.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-008 — Offline authentication and roles

- Decision ID: D-008
- Question: What account model should work on the offline LAN, and how should access be recovered?
- Options considered: Local DCMS accounts; recovery method not yet specified.
- Approved decision: Use local DCMS accounts. The System Administrator role is distinct from user roles; administrators may modify system settings, manage user accounts, and reset user passwords. Users receive separate role-based permissions. A second individually named administrator can reset the primary administrator. If all administrators are locked out, recovery is performed through a documented server-side procedure requiring two authorized people; the reset is logged and the administrator must set a new password.
- Reason: The owner specified local accounts and separate roles, then approved this recovery recommendation.
- Impacted files/modules: Authentication, account administration, password recovery.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-018 — Phase roadmap

- Decision ID: D-018
- Question: Should the project follow the development pack's Phases 0–8 or the proposal's Phases 0–10?
- Options considered: Use the pack roadmap; use the proposal roadmap; include the work from both roadmaps.
- Approved decision: Use the development pack's Phases 0–8 as the controlling sequence and include every proposal Phase 0–10 deliverable, mapped into the corresponding pack phase.
- Reason: The owner approved the recommendation to use the existing pack sequence and incorporate all proposal work.
- Impacted files/modules: `reference/PLAN.md`; phase planning and acceptance criteria.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-019 — ONP document code structure and registers

- Decision ID: D-019
- Question: Which ONP document code structure and register arrangement should govern new code assignments?
- Options considered: Follow the structure table in section 6 or use conflicting examples; include or omit the unit component for unit-level documents; combine or separate the Master Code Register and Document Control Register.
- Approved decision: Follow the section 6 structure in `reference/ONP_ORIGINAL_GUIDELINES.md`: `ONP-[SER]-[SEC]-[DOC_TYPE]-[SEQ]`, with `-[UNI]` included for unit-level documents. Use the official code list and keep the Master Code Register separate from the Document Control Register.
- Reason: The owner said to follow the guidelines and to keep the registers separate.
- Impacted files/modules: Code configuration and generation; Master Code Register; Document Control Register.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-020 — Revision numbering

- Decision ID: D-020
- Question: Which revision numbering scheme should DCMS use?
- Options considered: Major.Minor numbering or Rev 0 / Rev 1 numbering.
- Approved decision: Use Major.Minor numbering. Start at 1.0; increment minor revisions by 0.1; increment major revisions by one and reset the minor component to .0 (for example, 1.1 to 2.0).
- Reason: The owner specified 1.0 for the first major version, 1.1 for the next minor revision, and 2.0 when a major revision is applied.
- Impacted files/modules: Document version labels, code display, revision history, and registry reports.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved

### D-021 — Code uniqueness and source-list handling

- Decision ID: D-021
- Question: What uniqueness scope and source-list handling should apply to ONP codes?
- Options considered: Global or per-service uniqueness; use only listed codes or add/correct entries; resolve repeated source entries before seeding.
- Approved decision: Enforce global uniqueness and use codes from the official list without adding unlisted codes. Preserve every repeated or conflicting source entry as source data, mark all affected code values conflicted, and block them from new code assignments until ONP issues a corrected official list. Do not choose a winning entry or create substitute codes.
- Reason: The owner specified global uniqueness, official-list-only codes, and approved blocking all conflicted codes.
- Impacted files/modules: Organization/code-list configuration, code generation, seed data, and duplicate validation.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved
### D-010 — Document file formats and limits

- Decision ID: D-010
- Question: Which file types, size limit, and preview behavior should DCMS support initially?
- Options considered: PDF, DOCX, XLSX, images, and other formats; different per-file limits; inline preview or download only.
- Approved decision: Accept PDF, DOCX, XLSX, JPEG, and PNG files up to 50 MiB each. Do not provide inline previews. Store files privately, retain SHA-256 integrity hashes, and authorize downloads by the user's institution or exact unit document-register scope.
- Reason: The project owner approved the proposed Task 3.4 baseline.
- Impacted files/modules: Versioned document storage, upload validation, file download authorization, and deployment PHP upload limits.
- Approved by: Project owner
- Date: 2026-11-12
- Status: Approved
