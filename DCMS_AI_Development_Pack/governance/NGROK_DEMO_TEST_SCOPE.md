# ngrok Demo Test Scope

**Status: proposed scope for the temporary fake-data sharing session.** This is functional verification of the deployed build, not institutional UAT and not Phase 8 sign-off. No result here can be used as acceptance evidence for go-live.

## Environment under test

- One HTTPS ngrok dev URL forwarding only to `127.0.0.1:8000`; production frontend and Laravel API on the same origin.
- Isolated SQLite demo database seeded with fictional data; debug off; host allowlist and loopback trusted proxies enforced.
- Six tester logins (Process Owner, KMT, Division Head, MCC, RMIC, Staff Recipient) documented in the ignored `.local-private/dcms-demo-logins.txt`. The System Administrator account is not shared. No QMR login is seeded.
- Upload one file per request, accepted formats only, at most 2 MiB. Uploads and downloads count against the ngrok free-plan transfer limit.
- Demo data may be reset after the session; audit and notification content is fictional.

## In scope

| Area | What to verify | Primary role |
|---|---|---|
| Sign-in/session | Valid login, invalid login rejection, sign-out, session persists across navigation | All |
| Role/unit visibility | Only permitted navigation and records are visible; unit-scoped registry hides other units | All |
| Document request and code assignment | Create request, assign code from available components, confirm register separation and uniqueness | Process Owner, KMT |
| Version upload and revision | Accepted formats under 2 MiB upload with filename/hash recorded; rejected type or oversize is refused; a new revision does not overwrite the prior file | Process Owner |
| Lifecycle | Submit, review/return with reason, resubmit, approve, propose and confirm effective date, release — only the transitions your role permits | Process Owner, KMT, MCC |
| Separation of duties | Self-review/self-approval and prohibited repeat approval are rejected server-side | Process Owner, MCC |
| Distribution | Distribute a released version to an individual/role/unit; one recipient row per person; exact version retained | KMT |
| Acknowledgment | Named recipient opens the inbox and acknowledges; only that active recipient can act | Staff Recipient, others |
| Exemption | KMT exempts one pending recipient with a required reason; only that recipient is affected | KMT |
| Notifications | Notification is visible only to its intended user; read state persists after reload | All |
| Registry and reports | Registry reflects current state; KMT JSON summary and audit history load; unauthorized role is denied | KMT, others |
| Basic usability | Common tasks are understandable at a desktop width; validation messages and next actions are clear | All |

## Out of scope

- Retention schedule activation, automatic retention eligibility, and disposition execution (D-011/D-026 remain open; schedules stay inactive).
- Paper copy type/quantity, signatures, retrieval/surrender, and stamping (D-022 open).
- Claims that approvals or acknowledgments are legal electronic signatures (D-009 open).
- Production backup/restore, deployment, load/performance, MySQL concurrency, TLS/network hardening, and accessibility certification.
- System Administrator screens, and any workflow step requiring an unseeded QMR login.
- Real hospital, patient, employee, or ONP data; live document migration.

## Test cases

| ID | Scenario | Expected result |
|---|---|---|
| NG-01 | Sign in with each tester account; sign in with a wrong password; sign out | Only valid active accounts reach the app; wrong password is refused; sign-out ends the session |
| NG-02 | As a unit-scoped user, open the registry and try to reach another unit's document | Own-unit records only; cross-unit access is denied or not listed |
| NG-03 | Process Owner creates a document request | Record is saved, visible to KMT, and no code or approval is invented |
| NG-04 | KMT assigns a code from available components | Code uniqueness holds; Master Code Register and Document Control Register remain separate |
| NG-05 | Upload each accepted format just under 2 MiB, then a rejected type and a file over 2 MiB | Accepted files store with filename/hash; rejected type and oversize show a validation error |
| NG-06 | Start a revision on an existing document | Prior version remains intact and downloadable; new revision is separate |
| NG-07 | Run the permitted lifecycle transitions | Each transition requires the configured authority and shows the new status; release stays distinct from approval |
| NG-08 | Attempt self-review or a second prohibited approval | Server rejects the action and the accepted decision remains bound to the exact version |
| NG-09 | KMT distributes a released version to an individual plus a role/unit with overlap | One recipient row per person; exact version and targets retained |
| NG-10 | Recipient acknowledges; a different user attempts to acknowledge the same row | Only the named active recipient can acknowledge; receipt is not labeled training evidence |
| NG-11 | KMT exempts one recipient with a reason; retry without a reason | Exemption applies to that recipient only and records actor/reason; missing reason is refused |
| NG-12 | Open notifications as sender and recipient; mark read; reload | Only the intended user sees the notification; read state persists |
| NG-13 | KMT opens the JSON summary and filtered audit history; another role attempts the same | Authorized data loads; unauthorized access is rejected |
| NG-14 | Complete NG-01 through NG-13 at a normal desktop width | Labels, statuses, validation, and next actions are understandable; defects recorded |

## Entry, exit, and evidence

- **Entry:** tunnel up, login list distributed, Basic Auth issued, testers briefed on the fake-data rule.
- **Exit:** all in-scope cases attempted; no open critical security or data-integrity defect; blocked cases documented with the role/permission that blocked them.
- **Evidence per case:** case ID, tester, role, date/time, expected vs actual, screenshot or response reference, pass/fail/blocked.
- **Stop and report immediately:** cross-unit or cross-user data exposure, authentication bypass, upload validation bypass, or any sign that non-demo data is present.

## Known limits to record, not report as defects

- 2 MiB upload cap is a sharing constraint, not the product limit (50 MiB normally).
- No QMR account, so QMR-only steps cannot be exercised.
- Retention, paper-copy, legal-signature, backup, and reporting-export behavior are intentionally out of scope.
- A simulated or scripted check is not institutional UAT sign-off.
