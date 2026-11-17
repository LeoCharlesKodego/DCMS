# Submission Validation and Code Extraction Specification

**Status:** Approved  
**Decision basis:** D-010, D-019, D-021, D-029, D-030, and D-031  
**Scope:** Submission checks for PDF, DOCX, XLSX, JPEG, and PNG files. These checks do not themselves approve, register, release, or distribute a document.

## 1. Approved constraints

- Accept PDF, DOCX, XLSX, JPEG, and PNG files up to 50 MiB each. Do not provide inline preview (D-010).
- Extraction runs locally and offline. Phase 4B performs no OCR and sends no file content to an external extraction service (D-031).
- Extract document codes from PDF text layers, DOCX text, or XLSX cell text where available.
- A PDF with no extractable text layer is treated as scanned. JPEG/PNG files and scanned PDFs always require KMT manual code entry, which must be identified as manual.
- Only KMT enters or assigns a code. A submitter may update the document file to match the KMT-assigned code and resubmit; this reruns applicable checks and flags the updated file for KMT verification (D-031).
- Use the D-019 ONP code structure and official code lists. Enforce D-021 global uniqueness and block conflicted code values from new assignments. Never invent, normalize, or silently rewrite a code.
- The title-size default is 14 pt. KMT may edit the setting; setting changes require version history (D-030). This document defines checkability, not the settings-management implementation.

## 2. Submission context

- A new document version may be uploaded and saved as a draft before KMT assigns a code, but it cannot be submitted into the approval workflow until it contains a KMT-assigned code and the code check passes. Record this draft state as **“awaiting KMT code assignment.”** A document/revision with an existing KMT assignment must identify the existing document identity.
- **New document, no code in file, no KMT-assigned code yet:** this is an expected draft state, not an error or warning. Show “awaiting KMT code assignment” and route the draft to KMT for assignment from the approved official lists. Only KMT may enter or assign a code. After assignment, the submitter may update the document file to show that exact value and resubmit; until it matches, it remains a draft and cannot enter the approval workflow.
- **KMT-assigned code exists:** the code in the file must match the assigned code. A mismatch is a resolvable finding for KMT. KMT directs correction; the submitter may update the document file to match the assignment and resubmit, but may never choose, enter, or assign a code.
- If a code found in the file belongs to a different document, block the submission. KMT must resolve the identity conflict; the submitter cannot select or claim another document’s code.
- Global uniqueness and official-list conflict checks apply when KMT assigns a code and when comparing a code to other documents. They do not treat a revision’s reuse of its own document code as a duplicate or uniqueness violation.
- An existing document revision retains its document’s assigned code. A revision may not be treated as a new code assignment merely because it has a new version number.

## 3. Per-format extraction and checkability

Checks marked conditional are only reported as pass or fail when the input can be reliably evaluated. Otherwise the result must be exactly **“not checked, KMT to verify manually”**; an unevaluated rule must never be reported as pass.

| File type | Code extraction | Automatically checkable | 14 pt title-size check | Manual handling |
|---|---|---|---|---|
| **PDF with extractable text** | Read text-layer content, including document header/footer text where exposed by the parser. Do not infer a code from the filename. If the file has no extractable text layer, classify it as scanned. | Supported type/signature, size, readability, SHA-256 exact-file duplicate, and—when a code is due—code match to the KMT assignment, code structure/list validity, conflict status, collision with another document, and exact duplicate code. Exclude the same document’s own code for revisions. | Not checked. No supplied PDF fixture establishes a reliable title anchor. | KMT resolves warnings with a recorded reason. |
| **PDF with no extractable text (scanned/image-only)** | No extraction. Treat as scanned; no OCR. | Supported type/signature, size, readability, and SHA-256 exact-file duplicate. Code checks run only after KMT enters or assigns the code; compare against the document’s own assignment and other documents as applicable. | Not machine-checkable from the image; always show the manual status. | KMT manually enters/verifies the code and visually verifies formatting. |
| **DOCX** | Read document text from paragraphs, tables, and exposed headers/footers. Do not infer a code from the filename. | Supported type/signature, size, package readability, SHA-256 exact-file duplicate, and—when a code is due—match to the KMT assignment, code structure/list validity, conflict status, collision with another document, and exact duplicate code. Exclude the same document’s own code for revisions. | Check only a title value paired with the explicit “Document Title:” field in the document-control header block. Resolve effective font size through OOXML style inheritance and direct formatting. Otherwise show the manual status. | Unsupported or ambiguous containers are not assumed inspected; KMT resolves findings with a recorded reason. |
| **XLSX** | Read cell text throughout the workbook. Use displayed/cached cell values; do not execute formulas or macros. Do not infer a code from the filename. | Supported type/signature, size, workbook readability, SHA-256 exact-file duplicate, and—when a code is due—match to the KMT assignment, code structure/list validity, conflict status, collision with another document, and exact duplicate code. Exclude the same document’s own code for revisions. | Not checked. No supplied XLSX fixture or approved title-cell marker provides a reliable title anchor. | Formula cells without usable cached/displayed values and unsupported workbook structures are not treated as extracted text; KMT resolves findings with a recorded reason. |
| **JPEG / PNG** | No extraction, even if characters appear visible in the image; no OCR. | Supported image type/signature, size, readability, and SHA-256 exact-file duplicate. Code checks run only after KMT manual entry/assignment; same-document revision codes are not duplicate violations. | Not checked; always show the manual status. | KMT manually enters/verifies the code and visually checks formatting. |

### Title detection rule and fixture findings

- **DOCX rule:** Find the explicit `Document Title:` field in the document-control header block and pair it only with the title value in that same block. Resolve the value’s effective font size from OOXML direct formatting and the paragraph/character style chain. Do not infer a title from an arbitrary heading or the filename. If there is no unique paired value or effective size cannot be resolved, show “not checked, KMT to verify manually.”
- **PDF:** Not checked; the supplied fixtures do not include PDF title layouts or a validated title anchor.
- **PDF code extraction limitation:** The current local parser reads simple uncompressed PDF literal text operators only. It does not decode compressed content streams or font character maps. A PDF for which that limited parser returns no text is routed as scanned for KMT manual entry; treat PDF text-layer extraction as partial until a dedicated local parser is added and verified.
- **XLSX:** Not checked; no supplied workbook or approved title-cell marker establishes a reliable anchor.
- **JPEG/PNG:** Not checked; no OCR is performed and image content has no machine-readable title anchor.
- Fixture inspection found no unique `Document Title:` field in the General Guidelines, Specific Guidelines for Document Coding, Annex 1-3 register/forms, or Annex 6 policy/procedure template. Their title size is “not checked.” Annex 4 and Annex 5 contain the explicit field and a paired title value. Both fixture title values have direct OOXML size `w:sz=24` (12 pt); under the current 14 pt default they produce a formatting finding. Do not alter the source fixtures.

For every format, an exact-file hash match is a duplicate candidate, not permission to overwrite or silently discard either file. A code collision with a different document or a conflicted code is a hard block. Reuse of the assigned code by a revision of the same document is expected and is excluded from uniqueness/duplicate rejection. KMT assignment and code comparisons against other documents must enforce D-021.

## 4. Code candidate behavior

1. Search only content the local parser can read for that format. Candidate discovery uses the approved D-019 structure; filenames and metadata are comparison values, never code sources.
2. **One candidate, KMT code already assigned:** show the extracted value and location. Compare it with the assigned code; an exact match passes. A mismatch is a resolvable finding for KMT.
3. **New document has no in-file code and no KMT assignment:** save/upload as a draft and show “awaiting KMT code assignment.” This is expected, not an error, but it cannot be submitted into the approval workflow. KMT assigns the code; only then may the submitter update the document to contain that exact value and resubmit.
4. **New document contains one or more code-like values before KMT assignment:** treat them as candidates only. The submitter cannot select or claim a code. KMT determines whether an official code may be assigned; the document must then match that assignment before workflow submission.
5. **No candidate when a KMT code already exists:** report a mismatch. KMT resolves it; the submitter may update the document file to match the assigned value and resubmit, but may not enter a code in system fields or choose a different value.
6. **Multiple candidates:** show every candidate and location; do not select automatically. KMT resolves the document identity and assignment. If this is a new, unassigned document, keep it in “awaiting KMT code assignment.”
7. **Malformed candidate or invalid component:** show the value and failed rule. Do not normalize separators, case, components, or sequence. KMT resolves with a recorded reason and is the only role that enters or assigns codes. The submitter may update document content only to match KMT’s assignment.
8. **Candidate matches another document’s code:** hard block. KMT resolves document identity; no override permits assigning that code to a different document.
9. **Conflicted official-list value:** hard block for assignment or use as a new document’s code. Preserve source conflict; do not choose a source row or substitute a code.
10. **Code differs from filename/submission metadata:** retain all values and show a resolvable finding for KMT. Do not use filename or metadata to replace the code in the file or KMT’s assignment.
11. **Scanned PDF, JPEG, or PNG:** skip extraction. KMT manually enters/assigns the code, retains actor/time, and the system labels its source as manual. Run code-match and other-document conflict checks after entry.

## 5. File and parser edge cases

- **Extension/signature mismatch or unsupported format:** block the upload and identify the expected and detected type. Renaming a file does not convert it.
- **Over 50 MiB:** block before extraction and request a compliant file. Do not silently resize, recompress, or split the source.
- **Corrupt, truncated, or unreadable file/package:** block automatic checks and request a readable replacement. Do not return a passing result for checks that could not run.
- **Encrypted/password-protected or otherwise inaccessible content:** do not attempt password guessing or bypass protection. Block extraction until a readable supported submission is provided through an authorized process.
- **Valid file but unsupported internal element/container:** complete checks that are safe and available, mark affected extraction/format rules “not checked, KMT to verify manually,” and identify the unsupported content. Do not claim the entire document was inspected.
- **PDF yields no extractable text:** classify it as scanned/image-only regardless of its filename or declared subtype; no OCR; KMT manual code entry is mandatory.
- **Repeated code occurrences with the same value:** retain occurrence locations; present one candidate value with its locations for KMT review.
- **Different code values in one file:** treat as multiple candidates; do not select automatically.
- **Missing filename or misleading filename:** do not use it to create a code. Keep the uploaded file identity and extracted/manual code separate.
- **Exact-file duplicate hash:** flag the duplicate candidate for review; do not overwrite, delete, or suppress either file.
- **Potential code duplicate:** compare against other documents using D-021. Exclude the same document’s own code for a revision. A code belonging to another document is a hard block; KMT resolves document identity. Only KMT can assign codes.

### Parser safety

- Apply these configuration defaults before parsing: 30 seconds maximum processing time per file; 200 MiB maximum decompressed DOCX/XLSX package size; maximum 100:1 compression ratio; and 10,000 package entries. Enforce all limits before or during safe expansion. Fail closed if any limit is exceeded; do not return pass. These limits are approved defaults and may be changed only through authorized configuration.
- Do not execute macros, formulas, embedded scripts, or external content. Do not follow or fetch external links/relationships. Where a code candidate depends on unavailable external content, mark extraction “not checked, KMT to verify manually.” Macro-bearing or macro-enabled content is not accepted as DOCX/XLSX for this process.
- Validate package structure before parsing. Malformed, truncated, zip-bomb-like, or structurally inconsistent files fail safely: stop parsing, do not return pass, preserve the original upload for authorized review, and report a hard block when the file cannot be safely read.
- Run parsers with no network access and no write access to the uploaded source. Parsing must not alter the uploaded file.

## 6. Correction and rerun behavior

- The submitter never chooses, enters, or assigns a code in the system. Only KMT enters or assigns codes. The submitter may update the source document to include the KMT-assigned code, then upload/resubmit it. For scanned PDFs and JPEG/PNG, only KMT enters the code.
- On every replacement/resubmission, rerun all applicable checks: file/code match, code structure, official-list validity, conflict status, comparison with other documents, duplicate lookup, and mismatch findings against filename/submitted metadata. Recompute findings so stale pass/warning/block results are not carried forward.
- File-level type, size, readability, and hash checks remain tied to the exact uploaded bytes. They may be reused only while the file hash is unchanged; a changed/replaced file starts a new file-check result set.
- Preserve the original extracted candidate(s), KMT assignment and assigning actor/time, each uploaded file hash, upload actor/time, rerun results, and KMT flag in submission history. Never modify the uploaded file to apply a code correction.
- When a new document has no code and none has yet been assigned, retain it as a draft in “awaiting KMT code assignment.” KMT assigns from the official lists; after assignment, the submitter may update the document to show that exact value and resubmit for matching and KMT verification. It cannot enter the approval workflow before the assigned code appears in the document and matches.
- Any code entered by KMT rather than extracted from content is labeled as manually entered. KMT verification is required before the submission advances.

## 7. Findings and handoff to KMT

- **Pass:** the particular rule ran successfully and passed. A pass is never implied for a skipped, unsupported, or uncertain check.
- **Resolvable finding:** KMT must resolve the finding and record a reason. Examples: mismatch between the file code and KMT assignment, filename/metadata mismatch, exact-file duplicate candidate, ambiguous candidate codes, or an unevaluable formatting rule. A correction/resolution retains previous findings.
- **Hard block (no override):** wrong/unsupported file type, file over 50 MiB, unreadable/corrupt/encrypted or unsafe file, code belonging to a different document, or conflicted code. “No override” means resolution requires correcting the file or resolving its identity/source conflict and resubmitting; it never means waiving the finding. Do not mark a hard block passed.
- **Expected pending state:** a new document without an in-file code before KMT has assigned one. It is not an error; show “awaiting KMT code assignment” and route to KMT. Do not advance code verification until the assigned code appears in the file and matches.
- Store each finding with rule identifier, result, relevant value/location, file hash, and time. A correction creates a new result set while preserving the previous one.
- Once automatic checks complete, route the submission and all warnings/manual-entry findings to KMT verification. Automatic checks do not approve or release a document.

## 8. Required extraction test fixtures

Run extraction and formatting-checkability tests against each supplied ONP DOCX guideline/template fixture:

- `reference/source_originals/1_-General-Guidelines-for-Knowledge-Management_Cod.docx`
- `reference/source_originals/2_-KMT_2__Specific_Guidelines_for_Document_Coding_.docx`
- `reference/source_originals/Annex_1-3_2026.docx`
- `reference/source_originals/Annex-4_-Revised-Format-for-Guidelines-or-Manual_t.docx`
- `reference/source_originals/Annex-5_-Revised-Format-for-Specific-Guidelines_to.docx`
- `reference/source_originals/Annex-6_-Revised-Format-for-Policies-and-Procedure.docx`

The CSAC front page is a separate migration sample, not an ONP guideline fixture. It may be used as an additional parser-safety fixture but does not replace any required ONP file above.

For each required fixture, record whether the file opens safely, which code candidates and locations are found (if any), title-size checkability, any “not checked, KMT to verify manually” result, and any parser failure. Do not alter the fixture files.

## 9. Out of scope

- OCR, cloud extraction, or any network call for parsing.
- Automatic code assignment, code normalization, file correction, approval, release, or duplicate deletion.
- A decision to accept any format beyond D-010.
- Changing the source document or deciding unresolved ONP formatting/source conflicts beyond D-030's 14 pt title default.
