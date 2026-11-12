# DCMS AI Development Pack

**Project:** Institutional Document Control Management System (DCMS)  
**Document set:** AI development instructions and system specification  
**Version:** 1.0 — Planning baseline  
**Status:** Draft for review; not authorization to begin coding

## Purpose

This pack is the project source of truth for AI-assisted development. It describes the intended product, scope, workflows, constraints, quality gates, and the rules an AI coding assistant must follow.

## How to use this pack

1. Put this folder at the root of the project repository.
2. Give the AI coding assistant `AI_INSTRUCTIONS.md` as its primary instruction file.
3. Ask the AI to read the complete pack and summarize its understanding.
4. Resolve open decisions in `governance/OPEN_DECISIONS.md`.
5. Approve the architecture and detailed specifications before implementation.
6. Develop in small, reviewable phases. Require tests and evidence for each phase.

## File map

- `AI_INSTRUCTIONS.md` — mandatory behavior rules for coding AI.
- `specifications/PROJECT_VISION.md` — product purpose, goals, and scope.
- `specifications/PROPOSAL_BASELINE.md` — the project owner's original proposal/requirements baseline, with flagged differences from this pack.
- `reference/ONP_ORIGINAL_GUIDELINES.md` — the first institution's original document-control guidelines and templates (source material, not product behavior).
- `reference/ONP_CODE_LISTS.md` — that institution's service/section/unit/committee and document-type codes, copied as written with data-quality flags (seed data).
- `reference/source_originals/` — the owner's original `.docx` files, unchanged.
- `specifications/FUNCTIONAL_REQUIREMENTS.md` — functional requirements and acceptance criteria.
- `specifications/NON_FUNCTIONAL_REQUIREMENTS.md` — security, reliability, usability, and performance.
- `specifications/ROLES_AND_PERMISSIONS.md` — roles, permission scopes, and separation of duties.
- `specifications/BUSINESS_RULES.md` — system-wide rules.
- `architecture/SYSTEM_ARCHITECTURE.md` — proposed application and deployment architecture.
- `architecture/DOMAIN_MODEL.md` — conceptual entities and relationships.
- `architecture/TECHNICAL_DECISIONS.md` — provisional technology decisions and constraints.
- `workflows/WORKFLOW_ENGINE.md` — configurable workflow requirements.
- `workflows/DOCUMENT_LIFECYCLE.md` — document creation through archive.
- `workflows/EXISTING_DOCUMENT_MIGRATION.md` — migration process.
- `workflows/RECORDS_RETENTION.md` — records and disposal process.
- `governance/DEVELOPMENT_PROCESS.md` — phased vibe-coding method and change control.
- `governance/TESTING_AND_VERIFICATION.md` — testing rules and evidence.
- `governance/OPEN_DECISIONS.md` — unresolved decisions; do not guess.
- `prompts/PROJECT_INTAKE_PROMPT.md` — first prompt to give the coding AI.
- `prompts/PHASE_IMPLEMENTATION_PROMPT.md` — reusable prompt for each approved development phase.

## Status and authority

This pack is a planning baseline, not a legal compliance certification and not proof that the system meets any ISO standard. Applicable standards, Philippine laws, DOH requirements, and facility-specific regulations must be verified against authoritative current sources before being encoded as compliance claims.

When documents conflict, use this precedence:
1. Explicit user-approved decisions recorded in `governance/OPEN_DECISIONS.md` or a dated decision record.
2. This pack's approved specifications.
3. Existing verified project code and tests, where preserving behavior is required.
4. AI suggestions, which are proposals only.

The AI must not silently resolve a conflict by inventing a requirement.
