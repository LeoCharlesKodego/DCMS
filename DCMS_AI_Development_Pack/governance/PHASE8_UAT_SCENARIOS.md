# Phase 8 User Acceptance Test Scenarios

**Status: Draft scenarios; not executed.** A named ONP tester and a configured UAT environment are required to record actual results. Do not use patient charts or live confidential files as test data.

Record each scenario in `TESTING_AND_VERIFICATION.md` with tester, date, environment, actual result, pass/fail/blocked, and evidence reference.

| ID | Area | Scenario | Expected result |
|---|---|---|---|
| UAT-01 | Sign-in | Sign in with active local user; sign out; attempt invalid credentials | Authenticated access is established only for active valid accounts; logout ends the session |
| UAT-02 | Account security | Sign in as a newly created account with required password change | User is prompted to change the temporary password before using protected features |
| UAT-03 | Organization | Configure ONP units and assign a role with a unit scope | The account can perform only operations granted by its current role and unit assignment |
| UAT-04 | Document request | Process Owner creates a document request for an assigned unit | Request is recorded as pending code assignment; no code or approval is invented |
| UAT-05 | Code assignment | KMT assigns a code from a non-conflicted official component set | Master Code Register and Document Control Register remain separate and code uniqueness is enforced |
| UAT-06 | File/version | Upload an approved-format draft and begin a revision | Source filename and SHA-256 are recorded; new revision does not overwrite prior files |
| UAT-07 | Lifecycle | Submit, review, return, resubmit, approve, propose/confirm effective date, and release using a configured source-supported workflow | Each transition follows configured authority; approval and release are distinct; audit evidence remains visible |
| UAT-08 | Separation of duties | Attempt self-review/self-approval and prohibited repeated approval | Server rejects prohibited actions and records accepted decisions against the exact version |
| UAT-09 | Distribution | KMT distributes a released version to an individual, role, and unit with overlap | One recipient row is created per person; exact version and target-source history are retained |
| UAT-10 | Acknowledgment | Recipient opens distribution inbox and acknowledges receipt | Only the named active recipient can acknowledge; receipt is not represented as training evidence |
| UAT-11 | Exemption | KMT exempts one recipient and supplies a reason | Only that recipient's pending acknowledgment is exempted; the reason and actor are audited |
| UAT-12 | Migration | Import a source document with source metadata and possible duplicate | Original file/values remain available; duplicate candidates are flagged; no approval is inferred |
| UAT-13 | Migration verification | Reviewer records a correction, conflict, obsolete status, or verification note | Status and rationale are retained; unresolved issues remain in reconciliation output |
| UAT-14 | Migration registration | KMT registers a verified source item | Item appears as an unreleased draft with the source revision label and integrity hash |
| UAT-15 | External reference | Register an external reference and link it to a visible document | Reference and review dates are shown; updating the reference does not alter internal controlled files |
| UAT-16 | Operational records | Register non-patient operational-record metadata and place/release a hold | KMT can manage the record; active holds block disposition; no patient chart data is stored |
| UAT-17 | Retention | Review the inactive ONP three-year proposal and test a configured approved schedule only after owner approval | No schedule is active before approval; no automatic deletion occurs at expiry |
| UAT-18 | Reports | Open JSON summary and filtered audit history as KMT; attempt as an unauthorized user | Authorized report data is returned; unauthorized access is rejected |
| UAT-19 | Notifications | Receive a distribution notification and mark it read | Notification is visible only to its intended user; read state is saved |
| UAT-20 | Recovery | Restore the database and private files into a non-production environment, then check sample file hashes and workflows | Restore evidence proves both database and file integrity; production data is not overwritten |
| UAT-21 | Usability/accessibility | Complete common tasks at supported desktop widths using keyboard and assistive technology as available | Labels, validation, status, keyboard focus, and next actions are understandable; defects are recorded |

## Acceptance and sign-off

- The Project Owner appoints representative KMT, Process Owner, QMR, and other source-authorized testers for relevant scenarios.
- A scenario passes only when the tester records the observed result and evidence. A simulated backend test is not UAT sign-off.
- Critical security or data-integrity failures block acceptance and go-live.
- Project Owner signs the Phase 8 gate only after accepted UAT, migration reconciliation, backup/restore evidence, and deployment readiness review.
