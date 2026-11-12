# ONP Code Lists (Reference Institution Seed Data)

**Source:** `ONP-QMS-KMT-GUID-002` (Specific Guidelines for Document Coding), sections 4.2 to 4.8.
**Status:** Reference/seed data for the first institution. NOT hard-coded product data. Loading these lists into the system is configuration, and the lists must stay editable by the institution.
**Copied as written.** Codes and names are exactly as in the source. The "Flag" column marks data-quality issues found while copying. Nothing was corrected. Whether and how to fix them is open decision D-021 (uniqueness) and D-019 (code format).

Flags used: **LEN** = longer than the stated 3 letters, **DUP** = same code appears more than once in the source, **STRAY** = name begins with a stray "- " in the source, **CASE** = mixed case, **CONF** = conflicts with another list.

## 1. Service (division) codes (`SER`)

| Code | Name |
|---|---|
| MCC | Medical Center Chief Office |
| MED | Medical Service |
| NUR | Nursing Service |
| HOP | Hospital Operations & Personnel Support Service |
| AHP | Allied Health Professional Service |
| FIN | Finance Service |
| QMS | Quality Management System Committee |
| COM | Hospital Committees |

Note: examples in the source also use `AHPS`, and the text names `HOPSS`/`HOPPS`, which do not match `AHP`/`HOP`. See issue 12.3 in `ONP_ORIGINAL_GUIDELINES.md`.

## 2. Document type codes (`DOC_TYPE`)

| Code | Document type | Source usage example |
|---|---|---|
| POLC | Policy | ONP-QMS-KMT-POLC-001 |
| PROC | Procedure | ONP-NUR-PROC-015 |
| FORM | Form | ONP-MED-FORM-023 |
| MANL | Manual | ONP-QMS-MANL-001 |
| RECD | Record | ONP-AHPS-RECD-045 |
| GUID | Guideline | ONP-NUR-GUID-007 |
| WI | Work Instruction | ONP-HOP-WI-012 |
| ROR | Risk and Opportunity Register | ONP-ROR-PL-002 |

Also seen in the sources but not in this list: `FLCH` (flowchart), `CHKL` (checklist), `POL` (cited as `ONP-QMS-KMT-POL-001`), and the register-form letters `IF`, `EF`, `M`, `P`, `G`, `R`.

## 3. Section/unit codes by service (`SEC` / `UNI`)

### 3.1 Office of the Medical Center Chief (MCC)

| Code | Name | Flag |
|---|---|---|
| PET | Professional Education, Training, and Research Unit (PETRU) | |
| QIM | Quality Improvement | |
| IPC | Infection Prevention and Control | |
| CQI | Continuing Quality Improvement Committee (CQI) | |
| PSU | Patient Safety Unit | |
| PMU | Planning and Management Unit | |
| PHU | Public Health Unit (PHU) | |
| HESU | Hospital Epidemiology and Surveillance Unit (HESU) | LEN |
| HED | Health Emergency and Disaster Management Unit | |
| LEG | Legal Unit | |

### 3.2 Medical Service: direct departments

| Code | Name | Flag |
|---|---|---|
| ERD | Emergency Medicine Department | DUP (also Nursing, special areas) |
| OPD | Out-Patient Department | DUP (also Nursing, clinical areas) |
| MHU | Mental Health Unit | |
| ABT | Animal Bite Treatment Center | |
| FPU | Family Planning Unit | |
| SCU | Smoking Cessation | STRAY |
| PMR | Physical Medicine and Rehabilitation Department | |
| DEN | Dental Department | |
| WCP | Women and Children Protection Unit | |
| RTC | Red Top Center | |
| FMD | Family & Community Medicine Department | |
| HDU | Hemodialysis Unit | |
| MST | Medical Service - Training | |

### 3.3 Medical Service: clinical departments

| Code | Name | Flag |
|---|---|---|
| IMD | Internal Medicine Department | |
| ICU | Intensive Care Unit (ICU) | |
| RTU | Pulmonary/Respiratory Unit | |
| ASU | Acute Stroke Unit | |
| END | Endoscopy Unit | |
| PED | Pediatrics Department | |
| NIC | Neonatal Intensive Care Unit (NICU) | DUP (also Nursing special areas) |
| PIC | Pediatric Intensive Care Unit (PICU) | DUP (also Nursing special areas) |
| NEO | Neonatology | |
| SUR | Surgery Department | |
| OBG | OB-GYNE Department | |
| HRP | High Risk Pregnancy Unit | DUP-like (Nursing uses `HRPU`) |
| ANE | Anesthesia Department | |
| EEU | Electrophysiology Unit | STRAY |

### 3.4 Nursing Service: clinical areas

Note: `NRS` (Nursing, owner-confirmed) is used in the source examples but is not in this list or in 3.5.

| Code | Name | Flag |
|---|---|---|
| SOW | Surgical-Ortho Ward | |
| PWU | Pediatric Ward | |
| OBW | OB-GYNE Ward | |
| GWU | General Ward | |
| MWR | Medical Ward (Respiratory) | |
| MWN | Medical Ward (Non-Respiratory) | |
| CSSU | Central Supply & Sterilization Unit | LEN |
| OPD | Out-Patient Department | DUP |

### 3.5 Nursing Service: special areas

| Code | Name | Flag |
|---|---|---|
| OBC | Obstetrics Complex | |
| LRU | Labor Room | |
| DRU | Delivery Room | STRAY |
| HRPU | High-Risk Pregnancy Unit | LEN, DUP-like (Medical uses `HRP`) |
| OOR | Obstetrical OR/RR | |
| LIG | Ligation Room | |
| HYPER | Hyperbaric Unit | LEN |
| ERD | Emergency Room | DUP (Medical: Emergency Medicine Department) |
| ORU | Operating Room | |
| PAC | Post-Anesthesia Care Unit (PACU) | |
| NIC | Neonatal Intensive Care Unit (NICU) | DUP |
| MIC | Medical Intensive Care Unit (MICU) | |
| PIC | Pediatrics Intensive Care Unit (PICU) | DUP |
| NST | NS - Training | |

### 3.6 Hospital Operations and Patient Support (HOPSS/HOPPS)

| Code | Name | Flag |
|---|---|---|
| IMIS | Integrated Management Information System Section (IMISS) | LEN |
| HRM | Human Resource Management Section | |
| PRO | Procurement Section | |
| MMS | Materials Management Section | |
| SSU | Security Services | |
| AST | HOPPS - Training | |

### 3.7 Allied Health Professional Service

| Code | Name | Flag |
|---|---|---|
| PHD | Pharmacy Department | |
| NDD | Nutrition and Dietetics Department | |
| MSW | Medical Social Work Department | |
| MSE | MSWD ER/Admission Unit | |
| MSD | MSWD Discharge Services Unit | |
| MWM | MSWD Ward Management Unit | |
| MSO | MSWD OPD Unit | |
| HIM | Health Information Management Department | |
| HSU | Heart Station Unit | |
| ADM | Admitting Unit | CONF (structure examples use ADM as "Administrative") |
| RAD | Radiology & Imaging Science Department | |
| RAM | Mammography | |
| MRI | MRI | |
| RIR | Interventional Radiology | |
| PAT | Pathology Department | |
| BBU | Blood Bank | |
| PCU | Clinical Unit | name unclear in source |
| PML | Molecular Laboratory | |
| EFM | Engineering & Facilities Management Section | |
| LAU | Laundry Unit | |
| TRU | Transport Unit | |
| HKU | Housekeeping Unit | STRAY |
| AHT | AHPS - Training | |

### 3.8 Finance Service

| Code | Name | Flag |
|---|---|---|
| BUD | Budget Section | |
| ACC | Accounting Section | |
| BCU | Billing and Claims Section | |
| BIL | Billing Unit | |
| BCL | Claims Unit | |
| COS | Cash Operation Section | |

### 3.9 Quality Management System Committee

| Code | Name |
|---|---|
| PLA | Planning Team |
| IQA | Internal Quality Audit Team |
| KMT | Knowledge Management Team / Document Control Team |
| RMT | Relationship Management Team |
| QWT | Quality Workplace Team |
| CRT | Customer Relationship Team |

## 4. Hospital committees

### 4.1 Under the Medical Center Chief

| Code | Name | Flag |
|---|---|---|
| ART | Anti-Red Tape Act Committee (NSO) | |
| CODI | Committee on Decorum and Investigation (CODI) | LEN |
| CSAC | Consignment Selection and Awards Committee (Implant devices and medical supplies) | LEN |
| GRI | Grievance Committee | |
| HRD | Health Research and Development Committee | |
| HMB | Hospitalization and Medical Benefit Committee | |
| PSB | Human Resource Merit Promotion and Selection Board (HRMPSB) | |
| MHT | Medical Hyperbaric Team | DUP (also Chief of Medical Professional Staff) |
| MCCo | Medicines Consignment Committee | CASE, LEN |
| PMT | Performance Management Team | |
| PRD | Personnel/Human Resource Development Committee | |
| PRAISE | Program on Awards and Incentives for Service Excellence Committee (PRAISE) | LEN |
| CPC | Credentials and Privileging Committee | DUP (also Chief of Medical Professional Staff) |

### 4.2 Under the Chief of Medical Professional Staff

| Code | Name | Flag |
|---|---|---|
| ASC | Antimicrobial Stewardship Committee | |
| CPC | Credential and Privileging Committee | DUP (name spelled "Credential" here) |
| ORMC | Operating Room Management Committee | LEN |
| MHT | Medical Hyperbaric Team | DUP |
| BEM | BEMONC Training Committee | |

### 4.3 Under Nursing Service

| Code | Name | Flag |
|---|---|---|
| HCAT | Hospital Committee on Affiliation and Training of Students | LEN |
| LMC | Lactation Management Committee | |
| PPCN | Policy and Procedures Committee of Nursing Service | LEN |

### 4.4 Under Allied Health Professional Service

| Code | Name | Flag |
|---|---|---|
| BTC | Blood Transfusion Committee | |
| FORM | Forms Committee | CONF (same as document type `FORM`), LEN |
| RMIC | Records Management Improvement Committee | LEN |
| PHR | Patient Health Records Committee and Forms | |
| PTC | Pharmacy and Therapeutics Committee | |

### 4.5 Under Hospital Operations and Patient Support Service (HOPSS)

| Code | Name |
|---|---|
| HWM | Healthcare Waste Management Committee |
| ICT | Information and Communication Technology Committee |
| INS | Inspection Committee |
| IDC | Inventory and Disposal Committee |

### 4.6 Under Finance Service

| Code | Name |
|---|---|
| APC | Appraisal Committee |
| CAN | Canvassers Committee |
| HFC | Hospital Fees Committee |
| PMC | Price Monitoring Committee |

## 5. Summary of data-quality findings (for the intake review and D-019 / D-021)

- Codes longer than 3 letters: `HESU, CSSU, HRPU, HYPER, IMIS, CODI, CSAC, MCCo, PRAISE, ORMC, HCAT, PPCN, FORM, RMIC`.
- Codes appearing more than once: `ERD, OPD, NIC, PIC, CPC, MHT`. Near-duplicates: `HRP` and `HRPU`.
- Conflicts across lists: `FORM` (committee vs document type), `ADM` (Admitting Unit vs "Administrative" in structure examples), `KMT` (section code of QMS committee, also appears as the unit in `ONP-QMS-KMT-POLC-001`).
- The `Service` of the same code can differ by list (for example `OPD` under both Medical and Nursing), so uniqueness may need to be scoped by service (decision D-021).
- `NRS` (used in the source example `ONP-NUR-NRS-PROC-023`) means Nursing, confirmed by the owner, but it is missing from the Nursing Service list. `CLN` is also used in the examples, is in no list, and its meaning is unconfirmed.
