# ONP Original Guidelines (Reference Institution Source Material)

**What this is:** the institution's existing document-control guidelines and format templates, converted to Markdown from the owner's uploaded `.docx` files (kept unchanged in `reference/source_originals/`).
**Institution:** Ospital ng Palawan (ONP), a DOH hospital. The Knowledge Management Team (KMT) runs document control.
**Status:** SOURCE MATERIAL, not an approved product spec. It shows how the first institution works today, so the system can be configured to match it.

## How agents must use this file
- It is the **reference configuration** for the first institution. It is NOT product behavior to hard-code.
  Codes, stamps, roles, retention periods, and templates described here belong in **configuration/seed data**, never in application logic (see `AI_INSTRUCTIONS.md` section 5 and 7).
- Do not treat it as approved requirements. Rules here become requirements only after the owner decides the open items in `governance/OPEN_DECISIONS.md` (D-019 to D-023) and the intake review.
- Personal names of officeholders in the sources are intentionally omitted. Only roles are recorded. Officeholders are user data, not configuration.
- The source documents contain inconsistencies. They are listed in section 12. Do NOT silently pick one version.
- The code lists are in `ONP_CODE_LISTS.md`.

## 1. Source file inventory

| Source file (in `source_originals/`) | Document code | What it is |
|---|---|---|
| `1_-General-Guidelines-for-Knowledge-Management_Cod.docx` | ONP-QMS-KMT-GUID-001 | General Guidelines for Knowledge Management (the document control framework) |
| `2_-KMT_2__Specific_Guidelines_for_Document_Coding_.docx` | ONP-QMS-KMT-GUID-002 | Specific Guidelines for Document Coding (header says "General Guidelines for Knowledge Management", see issue 12.9) |
| `Annex_1-3_2026.docx` | (none shown) | Annex 1 Document Control Register, Annex 2 Document Registration and Request Form, Annex 3 Document Distribution and Retrieval Register. The forms are embedded as images. |
| `Annex-4_-Revised-Format-for-Guidelines-or-Manual_t.docx` | (template) | Format template for guidelines or manuals |
| `Annex-5_-Revised-Format-for-Specific-Guidelines_to.docx` | (template) | Format template for specific guidelines (titled "Document Coding") |
| `Annex-6_-Revised-Format-for-Policies-and-Procedure.docx` | (template) | Format template for policies and procedures |
| `CSAC_FRONT_PAGE.docx` | (none) | Front page only: "Guidelines on Consignment of Medical Supplies and Devices". Looks like a real document from the Consignment Selection and Awards Committee (CSAC). Useful as a **migration test sample**. |

Both guidelines list effective date August 26, 2026, next review date August 26, 2029, and a blank Revision Number.

## 2. Purpose and context (from GUID-001)
- Goal: a standard framework for controlling ONP documents: strict version control, regulatory compliance, less miscommunication.
- KMT has overseen document process control since October 2018.
- Stated challenges: individual cooperation in following procedures, system and human errors, and moving toward integrated electronic storage.
- Strategic objectives: keep 100% integrity and confidentiality of documented information, annual audit of all registered documents, and prevent use of obsolete documents.
- Core principle: the right version is identified and available at every point of use, and obsolete documents are prevented from unintended use.

### Strategic goals and KPIs

| Goal | KPI | Target | Timeline |
|---|---|---|---|
| Document integrity and reliability | Document Audit Accuracy Rate | > 95% | 2026 |
| Full compliance with standard formats | % of registered documents meeting standard format | 100% | 2026 |
| Document accessibility and version control | Availability of current versions at point of use | 100% | 2026 |

| KPI | Formula | Target | Frequency | Data source |
|---|---|---|---|---|
| Document Turnaround Time | Time from submission to release of Authorized Copy | <= 7 working days | Monthly | Document Control Register |
| Audit Compliance | (Matches in audit / total registered documents) x 100 | 100% | Annual | KMT Audit Report |
| Document Accuracy | Documents with correct codes and formatting | 100% | Quarterly | Inventory Sheet |

## 3. Scope, coverage, and exceptions
- Applies to all divisions, sections, units, and committees. Covers the full lifecycle: identification, creation, revision, registration, and safeguarding.
- Internal documents: Manuals, SOPs, Guidelines, Policies, Procedures, Working Instructions, Forms. Also relevant externally generated documents.
- Separate specific guidelines handle: Document Coding; Policy on Management and Control; Policy on Distribution and Retrieval.
- **Coding exceptions (GUID-002):** ICU-specific protocols and emergency protocols that need immediate implementation *before* formal coding registration.
- **Exempt from standard coding and formatting (GUID-001):**
  - Routing slips cascaded from the Central Office or higher authorities.
  - Daily census sheets (raw data tally logs).
  - Handwritten logbook entries (security or visitor logs).
  - Standardized clinical tools: internationally recognized scales or assessment tools with fixed copyrights.
  - Documents required by external regulators (DOH, PhilHealth, CSC) that use a mandatory template. They are registered with KMT but keep their external code or template (External Forms).
  - Units may ask KMT in writing to exempt specific forms, with a justification (for example technical necessity or a third-party requirement). KMT approves.
- No form may be used for official business without an authorized code, except exempt documents.

## 4. Roles and authority

| Role | What the sources say |
|---|---|
| **Medical Center Chief (MCC)** | Final approving authority for all Master Copies of manuals, SOPs, and guidelines (official signature). Accountable for overall effectiveness of the document control system. |
| **Quality Management Representative (QMR)** | Oversees the quality system. Reviews documentation before final approval. Accountable for review/verification and annual audit. |
| **Knowledge Management Team (KMT)** | The exclusive code authority. Registers, stamps, distributes, and controls all documents. Keeps digital Master List and prints a hard copy monthly as a contingency. Orients division and unit heads. Runs the annual audit (physical inventory vs register). Assigns codes after validating the DRRF. |
| **Process Owner** (division, section, or unit head) | Responsible for content, technical accuracy, sequence, numbering. Creates and revises documents. Keeps KMT register current. Reviews every 3 years or sooner. Keeps the list of authorized copies for the unit. Surrenders old authorized copies on revision. |
| **Division Head / Department Head / Service Chief** | Division Head is consulted on division-specific section codes and high-risk content. Department Head and Service Chief appear as reviewers on the policy and specific-guideline templates. |
| **Quality Management Committee** | Approves proposed codes for **high-risk** documents (patient safety, regulatory compliance, QMS processes). |
| **Management Committee** | Identifies the QMS requirements (documentation required). |
| **Forms Committee** | Approves internal forms before KMT coding. |
| **RMIC** (Records Management Improvement Committee) | Listed as the interface for archiving and disposal. |
| **Authorized Person** | Person in a unit responsible for safekeeping authorized copies. |
| **Supervisor / Unit Section Head** | Cascades documents to staff. Addresses non-compliance immediately. |

### RACI (GUID-001)
R = Responsible, A = Accountable, C = Consulted, I = Informed

| Activity | KMT | MCC | QMR | Process Owner | Staff |
|---|---|---|---|---|---|
| Document origination/revision | C | I | C | R | I |
| Review and verification | R | I | A | C | I |
| Approval (Master Copy) | I | A | C | I | I |
| Stamping and registration | R | I | I | I | I |
| Annual audit/inventory | R | I | A | C | I |

### RACI (GUID-002, coding)
| Activity | R | A | C | I |
|---|---|---|---|---|
| Document coding | KMT | MCC | Division Head | Staff |

## 5. Document control process
- High-level flow (SIPOC): **Originate -> Review/Code -> Approve -> Stamp/Register -> Distribute**.
- Outputs: stamped Master Copies, Authorized Copies, and an updated Document Control Register.
- Suppliers: Process Owners, Division Heads, DOH, ISO standards. Customers: hospital staff, external auditors (DOH, PhilHealth), patients.
- Internal interfaces: all divisions/units (registration and stamping), process owners (document submission), Management Committee (QMS requirements), RMIC (archiving/disposal). External: DOH (regulatory audits/licensing), PhilHealth (standards compliance/access), external partners (reference provision), external agencies (access requests).

### Process triggers

| Trigger | Source | Frequency | Responsible |
|---|---|---|---|
| New guideline/policy request | Internal (Process Owner) | As needed | Process Owner |
| Corrective/preventive action | Performance monitoring | As identified | KMT / Process Owner |
| 3-year periodic review | Quality requirement | Every 3 years | Process Owner |
| Regulatory change (DOH/ISO) | External (statutory) | As issued | KMT |

### Document status terms used
- **Master Copy:** original approved version, bears the MCC signature and a **Blue ink** stamp, kept by KMT in a secured cabinet.
- **Authorized Reproduced Copy:** reproduced from the master, **Purple ink** stamp, for reference only, not for further reproduction. Also called "Authenticated" copy in Annex 3.
- **Superseded:** replaced by a newer approved revision, stamped in **Red ink**.
- **Obsolete:** no longer in use because it was superseded or rescinded.
- **Uncontrolled:** any document without the Blue or Purple stamp. Must not be used for official processes.

## 6. Document coding rules (GUID-002)

### Structure
`ONP-[SER]-[SEC]-[DOC_TYPE]-[SEQ] Rev [MAJOR.MINOR]`
For units: `ONP-[SER]-[SEC]-[UNI]-[DOC_TYPE]-[SEQ] Rev [MAJOR.MINOR]`

| Component | Meaning |
|---|---|
| ONP | Fixed prefix for the institution |
| SER | 3-letter Service (division) code |
| SEC | 3-letter Section code |
| UNI | 3-letter Unit code |
| DOC_TYPE | "4-letter" document type code |
| SEQ | 3-digit zero-padded sequence (001, 015, 247) |
| Rev | Major (X.0): significant content or process change. Minor (X.Y): editorial, clarification, formatting only. |

Document type codes and the Service/Section/Unit/Committee lists are in `ONP_CODE_LISTS.md`.
The code must be placed exactly: policies/procedures/manuals in the right upper header box, forms in the right upper header corner, records in the footer right-aligned. All Arial 10 pt Bold.

### Coding request process (9 steps)
1. Process Owner identifies the need and confirms the requirement.
2. Process Owner completes the DRRF with the proposed code format and submits to KMT (email or hand delivery).
3. KMT logs the DRRF in the **Master Code Register**.
4. **Control Point 1:** KMT checks the Division, Section, and Document Type codes against the official lists. If invalid, KMT returns the DRRF within **24 hours** with correction instructions.
5. **Control Point 2:** KMT checks the Master Code Register for sequence availability and duplicates. If duplicate, KMT alerts the Process Owner and suggests alternatives.
6. KMT assigns the next available sequence number and builds the full code (example in source: ONP-NUR-NRS-PROC-023).
7. KMT emails the APPROVED code to the Process Owner and confirms registration. For **high-risk** documents, KMT also notifies Management (MCC/QMR) for additional review.
8. Process Owner receives the approved code (example: `... Rev 1.0`).
9. Process Owner applies the exact approved code on all document instances per the placement rules, then creates or revises the document.

## 7. Physical control rules (GUID-001)
- Manuals and guidelines are submitted in a black ring bind, transparent front cover, green back cover, with the official ONP watermark.
- Documents of 25 pages or more are printed back-to-back. Under 25 pages may be single-sided.
- Master Copies are kept in the secured KMT Document Cabinet (source names the Mental Health Unit as the location).
- No handwritten erasures, white-out, or manual corrections on any registered document. All changes go through the revision process.
- On receiving a new revision, process owners surrender the old Authorized Copies to KMT for disposal under the hospital shredding and recycling protocol.
- Each unit head keeps an updated list of authorized copies issued to the unit, accessible to staff at the point of use.
- All documented information is confidential. Disclosure to outside parties needs KMT or MCC clearance.

## 8. Retention and disposal
- Master and Authorized copies are generally retained **3 years or until rescinded/revised**.
- Single-sided obsolete documents: mark with a cross or "void", may be reused as scratch paper. Double-sided obsolete documents with sensitive information: shred immediately.
- Periodic review: every 3 years, or sooner if statutory requirements or procedures change.

## 9. Formatting standards

| Element | GUID-001 | Annex 4/5/6 "Technical Specifications" |
|---|---|---|
| Paper | A4 | A4 |
| Font | Arial. Size 12 Bold for titles, 11 for body | Arial 11 body, **Arial 14 title** (bold, ALL CAPS, centered) |
| Margins | Top, bottom, right 0.75". Left 1.25" | Top 0.75", Left 1.25", Bottom 0.75", Right 0.75" |
| Spacing | 1.15 | Single (1.15) |
| Paragraphs / tables | not stated | Justified. Tables bordered, Arial 10 |
| Page numbers | "Page 1 of 5" for stand-alone documents. Continuous paging for manuals. | Footer shows "CONFIDENTIAL RA 10173 PROTECTED" and "Page X of Y" |
| Binding | Black ring, transparent front, green back, ONP watermark | same |

## 10. Document template structure (Annex 4, 5, 6)

Every document starts with the same blocks. These are the fields a document record and generated document would need:

**Header box (repeated on every page):** Document Type, Doc. Control Code, Effective Date, Document Title, Next Review Date, Revision Number (and Page on some pages).

**Revision history table:** Version No., Review Date, Description of Change, Effectivity Date (about 22 blank rows).

**Signature block:** Originator; Reviewed (Annex 5 and 6 list "1. Department Head" and "2. Service Chief"); Approved.

**Distributed Authorized Copy list:** numbered copies 1, 2, 3...

| Template | Document type label | Main sections |
|---|---|---|
| Annex 4: Guidelines or Manual | GUIDELINES OR MANUAL | Background and Organization Context; Vision, Mission and Strategic Framework (3 goals max, KPI, target, timeline, core values); Organizational Structure and Interfaces; Process Management System (inputs and triggers, SIPOC); Core Contents (Scope and Purpose, Definitions, Applicability, Responsibility, RACI, General Guidelines); Annexes (each with a document code); References (newest first); Technical Specifications |
| Annex 5: Specific Guidelines | GUIDELINES | Objective; Scope and Purpose; Definitions; Applicability; Responsibilities; RACI; General Guidelines; Annexes; References; Technical Specifications |
| Annex 6: Policies and Procedures | POLICY | Objective; Scope and Purpose; Definitions (3-8 terms); Applicability (2-4 rows); Responsibilities; RACI; Policy Statements (3 mandatory); Process Inputs and Triggers (internal and external); Resources Required (3-5); Procedure Outline (3-5 steps: responsible, activity, interface); Procedure Detail (numbered steps with who and time); Process Control Points and Corrective Actions (2-4: control point, success criteria, if not met, corrective action, responsible); Process Outputs and Performance Indicators (3 KPIs minimum); Documentation and Records (record type, code, retention, storage, responsible); Enclosures/Forms; References; Technical Specifications |

Annex 4 to 6 reference: ISO 9001:2015 clauses 4.3, 4.4, 5.3, 7.5.2, 7.5.3, 9.1, 9.2, 10.2. Annex 6 also names RA 10173 and RA 11223 (Universal Health Care Act) as external triggers. Annex 4/6 list `FLCH` (flowchart) and `CHKL` (checklist) as enclosure code types.

**Applicability matrix (all templates):** Personnel, Services, Client/Patient Type, Location, Exceptions.

## 11. Register forms (Annex 1 to 3, read from the embedded images)
All three show the DOH / Ospital ng Palawan logo, a "DOCUMENT CONTROL CODE" box, and the footer "CONFIDENTIAL RA 10173 PROTECTED | Page X of Y".

**Annex 1: Document Control Register** columns: Document Control Code and Version; Document Title; Document Type (IF = Internal Form, EF = External Form, M = Manual, P = Policies, G = Guidelines, R = Reference); Origination (Office/Division-Unit/Committee); Date of Effectivity (MM/DD/YYYY); Date Registered (MM/DD/YYYY); Retention Period (Years); Remarks (SF = Superseded-Reference, SS = Superseded-Shredded, with date MM/DD/YY); Name and Signature of KMT.

**Annex 2: Document Registration and Request Form (DRRF)** fields: Document Name; Document Identification Code; Process Owner (Position, Division, Unit/Section). *Document Request:* Purpose of request, Date Requested, Date Returned, Returned by. *Document Registration:* Document Status (New / Revised), Document Type (same IF/EF/M/P/G/R letters), Date of Effectivity; "registered/requested by" Name, Position, Signature, Date. *Registration/Release (KMT only):* released/registered by (Name, Position, Signature, Date); returned document received by (Name, Position, Signature, Date); Remarks.

**Annex 3: Document Distribution and Retrieval Register** columns: Document Control Code and Version; Document Title; Copy Issued (Authorized / Authenticated); Number of Copies Issued; Distribution (Unit, Full Name, Signature, Date); Retrieval (Unit, Full Name, Signature, Date).

Form codes named in GUID-001: ONP-QMS-KMT-FORM-001 Document Control Register, -002 DRRF, -003 Revision History Form, -004 Document Distribution and Retrieval Register.

## 12. Inconsistencies and ambiguities in the source documents (FLAGGED, unresolved)

1. **Code structure vs examples.** The structure has Service, Section, Doc Type, Seq. Many examples skip the section (`ONP-NUR-PROC-015`, `ONP-QMS-MANL-001`, `ONP-HOP-WI-012`). `ONP-ROR-PL-002` and Annex 6's `ONP-FORM-ADM-001` and `ONP-RECD-AUD-001` use different component orders. (D-019)
2. **Section codes in examples not in the official list.** `NRS` (in `ONP-NUR-NRS-PROC-023`) means Nursing, as confirmed by the owner, but it is missing from the official section lists (the Nursing Service list only has specific wards and areas). `CLN` is also used in the examples and is in no list, and its meaning is unconfirmed. `ADM` is "Admitting Unit" in the list but "Administrative" in the structure examples. (D-019, D-021)
3. **Service code mismatches.** Official list uses `AHP` and `HOP`. Examples use `AHPS`, and the text names `HOPSS`/`HOPPS`. DRRF text says `[DIV]` where the structure says `[SER]`. (D-019)
4. **Document type codes.** Called "4-letter" but `WI` has 2 letters and `ROR` has 3. References cite `POL` (`ONP-QMS-KMT-POL-001`) while the list uses `POLC`. `FLCH` and `CHKL` are used in templates but not in the list. The Annex 1/2 forms use a different letter set (IF, EF, M, P, G, R). (D-019)
5. **Section/unit code lengths and duplicates.** Codes are said to be 3 letters, but many are 4 to 6 (`CSSU`, `HESU`, `HRPU`, `IMIS`, `HYPER`, `PRAISE`, `CODI`, `ORMC`, `HCAT`, `PPCN`) and one has mixed case (`MCCo`). The same code appears under different services or committees (`ERD`, `OPD`, `NIC`, `PIC`, `HRP`/`HRPU`, `CPC`, `MHT`). The committee code `FORM` equals the document type `FORM`. Some names have stray "- " prefixes. See `ONP_CODE_LISTS.md`. (D-021)
6. **Revision numbering.** Code uses `Rev [MAJOR.MINOR]` (Rev 2.0, Rev 1.1). The glossary says "Rev 0, Rev 1" and "Rev 0 = original approved issuance". The revision history column is "Version No." and the header field is "Revision Number". The DRRF example uses `Rev 1.0` for a new document. (D-020)
7. **Two registers.** The coding process uses a "Master Code Register" while GUID-001 defines the Master List / "Document Control Register". They may be the same thing or two registers. (D-019)
8. **Approval authority.** MCC is final approver of master copies, but GUID-002 says the Quality Management Committee approves codes for high-risk documents, and Annex 5/6 show Department Head and Service Chief as reviewers before approval. The exact approval chain per document type is not defined. (D-003, D-017)
9. **GUID-002 header title** says "General Guidelines for Knowledge Management" (same as GUID-001), though the content is about document coding. Annex 5 titles it "Document Coding".
10. **Annex numbering.** GUID-001 lists Annex 1 Control Register, 2 DRRF, 3 Revision History Form, 4 Distribution and Retrieval Register, 5 Format for guidelines or manual, 6 Revised format for specific guidelines, 7 Revised format for policies and procedures. The uploaded files are: Annex 1 Control Register, 2 DRRF, 3 Distribution and Retrieval Register, 4 Guidelines/Manual format, 5 Specific Guidelines format, 6 Policies/Procedures format. There is no Revision History Form file, and the DRRF code differs (`ONP-QMS-KMT-FORM-002` vs `ONP-QMS-FORM-001` in Annex 6). (D-023)
11. **Title size.** GUID-001 says Arial 12 Bold for titles. The annex specs say Arial 14. (D-023)
12. **Retention.** GUID-001 says 3 years or until rescinded/revised. Annex 6 samples show records retained 3 and 5 years. Annex 1 has a per-document "Retention Period (Years)" column. (D-011)
13. **Emergency and ICU protocols** may be implemented before formal coding registration (GUID-002 exceptions), but no process says how or when they must be registered afterward. (D-022)
14. **Copy terms.** "Authorized" and "Authenticated" copy are both used (Annex 3). Master Copy storage is named as the "Mental Health Unit".
15. **Typographic errors in sources** ("ORIGNATOR", "DIVICES", "Ownes", stray list markers). Preserve migrated documents as they are. Do not auto-correct.

## 13. Implications for DCMS (RECOMMENDATIONS ONLY, not approved requirements)
These show how the source rules could map to the system. Each needs owner approval first.

1. Treat ONP as the **first institution configuration**: its service/section/unit/committee lists, document types, and code format are loaded as seed data per institution, and other institutions can define their own.
2. A **code builder and validator** driven by configurable code components, mirroring Control Point 1 (components must exist in the official lists) and Control Point 2 (sequence uniqueness, no duplicates).
3. **Digital DRRF** replacing email/hand delivery, with the 24-hour return rule and the 7-working-day turnaround KPI tracked and reported.
4. **Document Control Register**, **Master Code Register**, and **Distribution and Retrieval Register** as system views/reports, matching the columns in section 11.
5. **Document header, revision history, signature block, and distributed-copy list** as structured fields, so generated documents and print outputs match the templates.
6. **Authorized copies** tracked per copy number, unit, recipient, issue date, and retrieval date, with retrieval of old copies on revision. Whether stamps (blue/purple/red) become digital watermarks or status labels is an open decision (D-022).
7. **High-risk document flag** that routes to MCC/QMR and requires committee approval of the code.
8. **3-year periodic review** schedule with configurable review period per document type.
9. **Annual audit** support: reconcile register vs physical/digital inventory.
10. **Exemption list** and **external form** handling: registered but keep their external code.
11. **Emergency/provisional issuance** path with mandatory later registration (needs a decision, D-022).
12. **Migration:** the existing ONP documents and the CSAC front page sample are early test inputs. Imported documents are never auto-approved.

## 14. References named in the sources
ISO 9001:2015; RA 10173 Data Privacy Act of 2012; RA 11223 Universal Health Care Act; DOH AO 2020-0047 Health Facilities Licensing Standards; ONP Quality Manual; ONP Document Control Policy (cited as `ONP-QMS-KMT-POL-001` and `ONP-QMS-POL-001`). All must be verified against current authoritative sources before any compliance claim. The source documents' own citations are not verification.
