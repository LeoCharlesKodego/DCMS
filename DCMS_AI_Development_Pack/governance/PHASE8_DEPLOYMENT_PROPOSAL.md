# Phase 8 Deployment and Backup Proposal

**Status: Planning proposal for ONP review; not approved for procurement, production configuration, or use with live records.** This proposal makes the deferred D-007 and D-013 decisions concrete for review. It does not change their open status in `OPEN_DECISIONS.md`.

## D-007: server and hosting boundary

### Proposed baseline

- Ubuntu Server 26.04.1 LTS, on institution-managed physical hardware.
- 8-core server CPU, 32 GB ECC RAM, and two 1.92 TB enterprise SSDs mirrored (RAID 1), expandable. Confirm actual document volume, concurrency, storage growth, and recovery capacity before purchase or allocation.
- One ONP installation on the internal network. Permit access only from approved ONP networks and managed endpoints. No public Internet exposure is proposed.
- TLS for all browser/API traffic, using an ONP-managed certificate and renewal owner. Keep application secrets outside source control; use distinct least-privilege OS and database service accounts.
- Keep MySQL data and private document storage inaccessible to ordinary users except through the application. Enable centralized time synchronization, security updates, log review, disk-space alerts, service health monitoring, and named operational ownership.
- Define the network diagram, firewall rules, certificate lifecycle, admin recovery contacts, maintenance window, and incident escalation before production deployment.

Ubuntu's official lifecycle page currently lists 26.04 LTS standard security maintenance through May 2031, with expanded maintenance through May 2036 subject to Ubuntu Pro. This supports it as a candidate, not a substitute for ONP's platform/support review. [Ubuntu release cycle](https://ubuntu.com/about/release-cycle)

### Approval and validation still required

ONP must confirm whether this is a physical server or an existing supported virtual environment, approve the OS and sizing, validate Linux administration capacity, and provide the actual network, certificate, storage, and monitoring design. The sizing above is a planning estimate only; no procurement or deployment is authorized by this proposal.

## D-013: proposed backup and recovery objectives

| Control | Proposed starting value | Acceptance condition |
|---|---|---|
| Full backup | Nightly encrypted backup of the MySQL database and complete private document storage | Backup manifest captures timestamp, scope, application/schema revision, sizes, and cryptographic hashes; job alerts on failure |
| Incremental recovery | MySQL binary logs archived at least every 15 minutes to a separate failure domain | Confirm logs are complete, encrypted, monitored, and retained alongside the full backup needed to replay them |
| Primary destination | ONP-managed backup appliance/NAS on a separate device and separate administrative credentials from the application server | ONP IT identifies and approves actual appliance, capacity, access control, and network path |
| Secondary copy | Encrypted offline or separately administered copy, updated daily; retain disconnected/immutable copies against ransomware | ONP IT selects medium/site, custody, rotation, and recovery access procedure |
| Retention | Daily recovery sets for 30 days and monthly recovery sets for 12 months | Records authority and DPO/legal review implications; reconcile with approved records policy before live data |
| Proposed RPO | 15 minutes for database transactions; last completed file backup for documents unless a file-level change journal is implemented | Owner accepts the different database/file recovery points and business impact |
| Proposed RTO | 4 hours to restore service after a declared incident, excluding procurement or site-wide outage | Timed isolated restore demonstrates the target with available staff and hardware |
| Restore exercise | Monthly sample restore to isolated environment; quarterly full database plus private-file restore and hash/reconciliation; record corrective actions | Named operator and reviewer sign each exercise; failed exercise blocks go-live |
| Encryption and access | Encrypt in transit and at rest; restrict backup read/restore access to named custodians; store keys separately with tested recovery access | ONP security owner approves key custody, rotation, and two-person emergency access |

This split plan is necessary because the system stores both relational workflow/audit data and private document files; a valid recovery set must reconcile both. MySQL's documentation describes point-in-time recovery as restoring a full backup and replaying subsequent binary logs, and notes that those logs must be retained and locatable. [MySQL point-in-time recovery](https://dev.mysql.com/doc/refman/8.4/en/point-in-time-recovery-binlog.html), [MySQL backup and recovery](https://dev.mysql.com/doc/refman/8.4/en/backup-and-recovery.html)

The proposed values are a starting point only. ONP must select a real destination and failure domain, confirm capacity and owners, approve retention and key custody, and accept the RPO/RTO in light of operating hours and document impact. This proposal does not configure or claim any backup job. D-011 retention of controlled records remains separate from backup-set retention; neither policy authorizes automatic record disposition.

## Go-live evidence checklist

Before 8.7/8.8 can be considered:

1. Record signed D-007 and D-013 decisions and the actual environment details.
2. Run UAT with named ONP representatives against `PHASE8_UAT_SCENARIOS.md`; record pass/fail, defects, and acceptance.
3. Configure backups only in the approved environment. Attach successful manifests, failure alerts, and timed restore evidence for both database and private files.
4. Complete the migration reconciliation on an isolated MySQL/UAT environment, including approved metadata and source authority decisions; keep unresolved source conflicts blocked.
5. Deliver role-based training and record attendance/completion.
6. Review security, TLS/network controls, access assignments, monitoring, recovery contacts, change/rollback plan, and unresolved defects.
7. Obtain explicit owner go-live approval for the named release, environment, migration/import scope, and maintenance window.
