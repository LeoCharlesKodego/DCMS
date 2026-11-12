# Open Decisions Register

The AI must not silently guess these decisions. Record each answer, date, and approval status here.

| ID | Decision | Current status | Notes |
|---|---|---|---|
| D-001 | Exact healthcare facility type | Open | Hospital, clinic, diagnostic laboratory, multi-specialty, other. EVIDENCE ONLY, not confirmed by owner: the source guidelines in `reference/` are from Ospital ng Palawan (a DOH hospital). Owner must confirm it is the first/pilot institution |
| D-002 | Institution size and expected departments/users | Open | Needed for scale and configuration defaults |
| D-003 | Approval governance model | Partially open | Institution head, QMS committee, clinical committee, delegated authorities, or combinations. EVIDENCE ONLY: ONP source shows Medical Center Chief as final approver, QMR as reviewer, Department Head and Service Chief as reviewers on policies/guidelines, Quality Management Committee approving codes of high-risk documents. Exact chain per document type is not defined (`reference/ONP_ORIGINAL_GUIDELINES.md` section 4) |
| D-004 | Can institutions fully customize workflows? | Approved in principle | Yes: reviewers, sequence, parallel stages, and final authority should be configurable |
| D-005 | One institution per installation or multi-tenant platform? | Open | Major data isolation and architecture decision |
| D-006 | Final technology stack | Open | Candidate: Laravel, React, Tailwind, MySQL/MariaDB. Proposal baseline section 14 also lists TypeScript, Vite, REST/JSON |
| D-007 | Server operating system and hardware | Open | Local server specifications needed |
| D-008 | Authentication and account recovery on offline LAN | Open | Define local admin recovery and password policy |
| D-009 | Electronic signature requirements | Open | Determine whether typed approval, authenticated action, or legally compliant digital signature is required |
| D-010 | Document file formats, size limits, and preview needs | Open | PDF, DOCX, XLSX, image, other |
| D-011 | Retention schedules and applicable legal bases | Open | Must be institution/facility-specific and verified. EVIDENCE ONLY: ONP source says 3 years or until rescinded/revised, with a per-document retention column; sample records show 3 and 5 years. Not verified |
| D-012 | Mobile access requirement | Open | Desktop-first or responsive mobile use |
| D-013 | Backup schedule, destination, RPO, and RTO | Open | Include restore testing |
| D-014 | Applicable ISO edition and healthcare regulations | Open | Verify current authoritative editions and applicability before compliance mapping. Proposal baseline sections 15-16 also name ISO 10013, Philippine National Standards, DOH issuances, and the Data Privacy Act of 2012 (RA 10173) / NPC issuances; DPO and legal adviser to evaluate |
| D-015 | Existing documentation status and volume | Open | Does the institution already have ISO/QMS documents, and about how many? Drives migration scope and storage (Proposal section 20, Q4-Q5) |
| D-016 | Required reports and export formats | Open | Which of the ten reports named in Proposal section 13 are required; export formats (Proposal section 20, Q14) |
| D-017 | Approval sequence and quorum rules | Open | Sequential, parallel, or both; minimum approvals. D-004 approves configurability in principle; exact rules to be finalized with the institution (Proposal section 7.1, Q11) |
| D-018 | Phase roadmap: pack Phases 0-8 or proposal Phases 0-10 | Open | See the comparison in specifications/PROPOSAL_BASELINE.md. Decide before Phase 2 tasks are written |
| D-019 | Document code format and component rules | Open | ONP source examples conflict with its own structure: missing section components, `[SER]` vs `[DIV]`, document types of 2 to 4 letters, `Master Code Register` vs `Document Control Register`. Decide the authoritative structure, required components, and that code formats are configurable per institution (`reference/ONP_ORIGINAL_GUIDELINES.md` sections 6 and 12) |
| D-020 | Revision numbering scheme | Open | Major.Minor (Rev 1.0, Rev 1.1) vs Rev 0, Rev 1. Define Major vs Minor, and how it maps to the "Version No." and "Revision Number" fields |
| D-021 | Code uniqueness scope and cleanup of duplicate or overlong codes | Open | ONP lists repeat ERD, OPD, NIC, PIC, CPC, MHT, and many codes exceed 3 letters (`reference/ONP_CODE_LISTS.md`). Decide global vs per-service uniqueness and whether lists are corrected before seeding. `NRS` is confirmed by the owner as Nursing but is missing from the lists; owner decides whether to add it (`CLN` meaning unconfirmed) |
| D-022 | Physical copy controls vs digital equivalents | Open | ONP uses blue/purple/red ink stamps, authorized copies, monthly hard-copy printouts, shredding, and emergency/ICU protocols used before coding. Decide what becomes digital, what stays on paper, and the provisional-issuance rule |
| D-023 | Corrections to the source documents | Open | Annex numbering mismatch, DRRF form code mismatch, title size 12 vs 14, GUID-002 header title. Owner decides which version is authoritative. The system never auto-corrects migrated documents |

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
