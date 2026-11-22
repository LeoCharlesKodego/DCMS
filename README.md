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



## Local development environment

The committed repository contains no PHP runtime and no `backend/.env`. Both are
ignored on purpose and must be recreated on a new machine. Verified versions:

| Tool | Version used | Notes |
|---|---|---|
| PHP | 8.5.12 NTS x64 | The dev scripts look for `.local-tools/php-8.5.12/php.exe`. PHP 8.4.1 or newer works when `PHP_BIN` is set. |
| Composer | 2.11.2 | Installed as `.local-tools/composer.phar`. |
| Node.js | 26.13.1 | `frontend/package.json` uses Vite 8 and TypeScript 7. |
| npm | 11.20.0 | |
| Database | SQLite for local tests | The approved production engine is MySQL 8.4 (D-006); feature tests and the local harness use SQLite. |
| Playwright | 1.65.1 | Chromium, Firefox, and WebKit. |

### Recreating the PHP runtime

```bash
# 1. The zip, extracted so php.exe is at .local-tools/php-8.5.12/php.exe
curl -L -o php.zip https://windows.php.net/downloads/releases/archives/php-8.5.12-nts-Win32-vs17-x64.zip
unzip php.zip -d .local-tools/php-8.5.12

# 2. The extension DLLs must sit next to php.exe: Windows resolves their dependent
#    DLLs (libsqlite3, libcrypto, ...) from the PHP root, not from ext/.
cd .local-tools/php-8.5.12
for e in curl fileinfo gd intl mbstring openssl pdo_sqlite sqlite3 zip; do
  cp "ext/php_$e.dll" .
done
```

`.local-tools/php-8.5.12/php-test.ini` is also ignored. It must enable the
extensions Laravel and SQLite need and point `extension_dir` at the PHP root
(`D:/...` or the absolute path of the checkout):

```ini
extension_dir = "D:/DCMS/.local-tools/php-8.5.12"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
memory_limit = 512M
```

If `php -m` reports `VCRUNTIME140.dll ... is not compatible`, install the current
Microsoft Visual C++ 2015-2022 Redistributable (x64).

Composer:

```bash
curl -L -o composer-setup.php https://getcomposer.org/installer
PHPRC="D:/DCMS/.local-tools/php-8.5.12/php-test.ini" \
  .local-tools/php-8.5.12/php.exe composer-setup.php \
  --install-dir=.local-tools --filename=composer.phar
```

### Recreating `backend/.env`

`backend/scripts/dev-local.sh` exports the SQLite and local-server settings for the
run, but Laravel still needs an application key:

```bash
cd backend
cp .env.example .env
PHPRC="D:/DCMS/.local-tools/php-8.5.12/php-test.ini" \
  ../.local-tools/php-8.5.12/php.exe artisan key:generate
```

`APP_KEY` is the only value that must be generated. The script overrides
`APP_ENV`, `APP_URL`, `DB_CONNECTION`, `DB_DATABASE`, `SESSION_DRIVER`,
`CACHE_STORE`, and `QUEUE_CONNECTION`, and writes the disposable local account
password to `backend/.env.dev-local` (also ignored). See `frontend/README.md` for
starting the API and client.

### Install and test

```bash
cd backend && php ../.local-tools/composer.phar install
cd ../frontend && npm install && npx playwright install chromium firefox webkit
cd .. && bash backend/scripts/dev-local.sh --prepare-only
cd frontend && npm run build && npx playwright test
cd ../backend && php artisan test
```
