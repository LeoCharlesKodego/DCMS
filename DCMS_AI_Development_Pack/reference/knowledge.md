# Project Knowledge: DCMS (READ FIRST, EVERY TASK)

**Project:** Institutional Document Control Management System (DCMS)
**Status:** PLANNING BASELINE. Do NOT write application code until the Phase 1
approval gate in `PLAN.md` is ticked by the user.
**Stack:** NOT DECIDED (open decision D-006). Laravel/React/TypeScript/Vite/MySQL in the pack and proposal is only a
candidate. Never assume it, scaffold it, or install it.

## Read order (main agent, before anything else)
1. This file
2. `DCMS_AI_Development_Pack/AI_INSTRUCTIONS.md`: mandatory full rules. Read it completely.
3. `PLAN.md`: find the FIRST unchecked task. That is the ONLY task to work on.
4. ONLY the pack files relevant to this task (see Spec map). Do not load the whole pack.
5. `DCMS_AI_Development_Pack/governance/OPEN_DECISIONS.md` before touching anything
   listed there. Never guess an open decision. Ask me.

## Authority
- Precedence (from the pack): 1) decisions I approved in OPEN_DECISIONS.md,
  2) the pack's approved specs, 3) verified existing code/tests, 4) AI suggestions
  (proposals only).
- `specifications/PROPOSAL_BASELINE.md` is the project owner's original proposal. It ranks with the
  pack's specs (level 2). Where it differs from other pack files, the differences are listed at its top
  and tracked as open decisions (D-006, D-014, D-015 to D-018). Do NOT pick a side. Ask me.
- `reference/` holds the first institution's (ONP) original guidelines, templates, and code lists.
  It is SOURCE MATERIAL for configuration and seed data, not product behavior and not approved
  requirements. Its own inconsistencies are listed in `ONP_ORIGINAL_GUIDELINES.md` section 12
  and tracked as D-019 to D-023. Do NOT pick a version silently. Ask me.
- Work from the Markdown files. Do not try to read `reference/source_originals/*.docx`.
- This file ADDS scope/agent rules. If this file and the pack differ, follow the
  stricter rule and tell me about the conflict. Do not silently pick one.

## Spec map (read only what the task needs)
| Task area | File |
|---|---|
| Purpose, scope | specifications/PROJECT_VISION.md |
| ONP original guidelines: roles, RACI, coding process, templates, register forms, KPIs, flagged issues | reference/ONP_ORIGINAL_GUIDELINES.md |
| ONP service/section/unit/committee and document-type codes (seed data, flagged) | reference/ONP_CODE_LISTS.md |
| Original proposal: 18 deliverables, named reports, 15 client questions, roadmap, privacy/ISO references | specifications/PROPOSAL_BASELINE.md |
| Features, acceptance criteria | specifications/FUNCTIONAL_REQUIREMENTS.md |
| Security, performance, reliability | specifications/NON_FUNCTIONAL_REQUIREMENTS.md |
| Roles, scopes, separation of duties | specifications/ROLES_AND_PERMISSIONS.md |
| System-wide rules | specifications/BUSINESS_RULES.md |
| Layers, deployment, modules | architecture/SYSTEM_ARCHITECTURE.md |
| Entities, relationships | architecture/DOMAIN_MODEL.md |
| Tech choices (provisional) | architecture/TECHNICAL_DECISIONS.md |
| Workflow engine | workflows/WORKFLOW_ENGINE.md |
| Document lifecycle | workflows/DOCUMENT_LIFECYCLE.md |
| Migrating existing documents | workflows/EXISTING_DOCUMENT_MIGRATION.md |
| Records, retention, disposal | workflows/RECORDS_RETENTION.md |
| Phases, change control | governance/DEVELOPMENT_PROCESS.md |
| Tests, evidence, release gate | governance/TESTING_AND_VERIFICATION.md |
| Prompt formats | prompts/ |
(all paths are inside `DCMS_AI_Development_Pack/`)

## DCMS hard rules (always apply, even if you read nothing else)
- DCMS controls institutional documents. It is NOT an EMR. No patient charts,
  histories, prescriptions, or lab results as ordinary records.
- Upload or migration does NOT equal approval. Approval, authorization, effective
  date, and release are separate events.
- Released versions are never silently edited or replaced. Revisions link to the previous version.
- Admin privileges do NOT grant approval authority or access to confidential content.
- Enforce authorization on the server/API, not only in the UI.
- Never hard-code departments, approval chains, document codes, or retention periods.
- Retention expiry does not authorize destruction.
- No compliance claims (ISO, legal, DOH, privacy) without verified sources and evidence.
- Institution-specific values (ONP codes, code format, stamps, roles, retention periods, templates) are
  CONFIGURATION / SEED DATA. Never hard-code them in application logic.
- No cloud services for core workflows. The system must work on an offline LAN.

## Scope rules (strict)
- Work on ONE task from PLAN.md at a time. When done, tick it and STOP.
- Only edit files directly needed for the current task or the error being fixed.
- Do NOT refactor, rename, reformat, or "clean up" unrelated code.
- Do NOT delete files, tables, data, or tests without asking first.
- Do NOT install packages/frameworks, run migrations, or change DB schema without approval.
- Do NOT edit existing pack files except where the task in PLAN.md says so.
- If something is not in the pack, ask. Do not invent requirements.
- If the fix needs files outside the plan, STOP and tell me which files and why.

## No guessing
- Never guess file paths, function names, tables, routes, APIs, or library methods.
  Inspect the repo first. If it can't be confirmed, say so.
- Label statements as: verified fact / assumption / recommendation / open decision.
- Never claim a test passed unless you ran it and saw the result.

## Before editing: write this plan (3-6 lines), then wait for my OK on risky work
- Task: <from PLAN.md>
- Files I will edit (ALLOWLIST): <list>
- Files I will only read: <list>
- Risks (DB, security, data loss): <list or "none">
- How I will verify: <command or check>
Architecture, database, security, dependency, or data changes ALWAYS need my approval first.

## Multi-agent rules
Freebuff splits work across specialized agents (finding files, planning, editing,
reviewing, etc.). Names may differ, but these rules apply to ALL of them:
- **Pass this file's rules and the allowlist to every sub-agent in its prompt.**
  Sub-agents do not automatically know the project rules.
- **File-finder / researcher / thinker agents:** read-only. They never edit.
- **Editor agents:** edit ONLY allowlisted files. If another file seems needed,
  stop and report to the main agent. Do not edit it.
- **Reviewer agent:** check the full diff and FAIL the change if it contains files
  outside the allowlist, unrelated formatting/renames, unapproved deletions,
  new dependencies, hard-coded institution data, or anything that breaks a DCMS hard rule.
- Do not spawn agents for a trivial one-file fix.

## Anti-looping
- If the same fix fails twice, STOP. Do not try a third variation.
  Report: what you tried, the exact error, your best guess at the cause.
- Never "fix" an error by deleting the check, silencing it, or weakening a test.
- Never undo and redo the same change repeatedly. If stuck, ask me.

## Verification and completion
- Run relevant tests/checks and show real output. List tests not run and why.
- Finish with the pack's completion report (AI_INSTRUCTIONS.md section 8):
  Outcome, Files changed, Database changes, Verification, Security considerations,
  Known limitations, Rollback.
- Confirm with `git diff --stat` that only allowlisted files changed.
- Tick the task in PLAN.md, then STOP. Do not start the next task or phase.

## Git
- Conventional commits: `feat:`, `fix:`, `chore:`, `docs:`. One logical change per commit.
- Never commit secrets, `.env`, uploaded documents, or database files.
- No destructive git commands (`reset --hard`, `clean -fd`, force push) without asking.
