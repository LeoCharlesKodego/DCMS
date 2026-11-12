# DCMS AI Development Pack

**Project:** Ospital ng Palawan Document Control Management System (ONP DCMS)  
**Document set:** AI development instructions and system specification  
**Version:** 1.0 â€” Planning baseline  
**Status:** Owner authorized initial implementation on 2026-11-11; unresolved decisions remain tracked in `governance/OPEN_DECISIONS.md`.

## Purpose

This pack is the project source of truth for AI-assisted development. It describes the intended product, scope, workflows, constraints, quality gates, and the rules an AI coding assistant must follow.

## How to use this pack

1. Put this folder at the root of the project repository.
2. Give the AI coding assistant `AI_INSTRUCTIONS.md` as its primary instruction file.
3. Ask the AI to read the complete pack and summarize its understanding.
4. Resolve open decisions in `governance/OPEN_DECISIONS.md`.
5. Approve the architecture and detailed specifications before implementation.
6. Develop in small, reviewable phases. Require tests and evidence for each phase.

## Source guidelines

The guidelines/ folder contains the supplied source DOCX files for the institutional knowledge management and document coding guidance, including the front page and Annexes 1–6. Treat these as source references; verify applicability and resolve conflicts through the approved project decisions.

## Supplemental references

The `reference/` folder contains the supplied project plan, knowledge notes, ONP code lists, and original ONP guidelines.

## File map

- `AI_INSTRUCTIONS.md` â€” mandatory behavior rules for coding AI.
- `specifications/PROJECT_VISION.md` â€” product purpose, goals, and scope.
- `specifications/FUNCTIONAL_REQUIREMENTS.md` â€” functional requirements and acceptance criteria.
- `specifications/NON_FUNCTIONAL_REQUIREMENTS.md` â€” security, reliability, usability, and performance.
- `specifications/ROLES_AND_PERMISSIONS.md` â€” roles, permission scopes, and separation of duties.
- `specifications/BUSINESS_RULES.md` â€” system-wide rules.
- `architecture/SYSTEM_ARCHITECTURE.md` â€” proposed application and deployment architecture.
- `architecture/DOMAIN_MODEL.md` â€” conceptual entities and relationships.
- `architecture/TECHNICAL_DECISIONS.md` â€” provisional technology decisions and constraints.
- `workflows/WORKFLOW_ENGINE.md` â€” configurable workflow requirements.
- `workflows/DOCUMENT_LIFECYCLE.md` â€” document creation through archive.
- `workflows/EXISTING_DOCUMENT_MIGRATION.md` â€” migration process.
- `workflows/RECORDS_RETENTION.md` â€” records and disposal process.
- `governance/DEVELOPMENT_PROCESS.md` â€” phased vibe-coding method and change control.
- `governance/TESTING_AND_VERIFICATION.md` â€” testing rules and evidence.
- `governance/OPEN_DECISIONS.md` â€” unresolved decisions; do not guess.
- `prompts/PROJECT_INTAKE_PROMPT.md` â€” first prompt to give the coding AI.
- `prompts/PHASE_IMPLEMENTATION_PROMPT.md` â€” reusable prompt for each approved development phase.

## Status and authority

This pack is a planning baseline, not a legal compliance certification and not proof that the system meets any ISO standard. Applicable standards, Philippine laws, DOH requirements, and facility-specific regulations must be verified against authoritative current sources before being encoded as compliance claims.

When documents conflict, use this precedence:
1. Explicit user-approved decisions recorded in `governance/OPEN_DECISIONS.md` or a dated decision record.
2. This pack's approved specifications.
3. Existing verified project code and tests, where preserving behavior is required.
4. AI suggestions, which are proposals only.

The AI must not silently resolve a conflict by inventing a requirement.


