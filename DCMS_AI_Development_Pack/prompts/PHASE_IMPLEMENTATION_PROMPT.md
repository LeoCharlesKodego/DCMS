# Reusable Prompt — Implement One Approved Phase

We are implementing one approved DCMS development phase.

Before changing anything:
1. Read `AI_INSTRUCTIONS.md` and the relevant specifications.
2. Inspect the current repository, Git status, dependencies, and existing implementation.
3. Identify the exact requirements and acceptance criteria for this task.
4. List the exact files you expect to change and explain why.
5. Identify database, security, compatibility, and data-loss risks.
6. Do not proceed with architecture changes, migrations, dependency installation, destructive operations, or changes outside the approved scope without asking.

Implementation:
- Make the smallest coherent change.
- Preserve existing working behavior and conventions.
- Enforce business rules on the server.
- Add or update automated tests.
- Update relevant documentation.
- Do not fabricate test results.

Verification:
- Run the relevant test suite, static analysis, and build commands available in the repository.
- Inspect the diff and check for unrelated changes.
- Report exact commands and actual outputs/results.
- Identify tests not run and why.

Final report:
1. Outcome.
2. Files changed.
3. Database changes and data impact.
4. Tests run and results.
5. Security and permission checks.
6. Known limitations.
7. Rollback plan.
8. Requirements still outstanding.

Stop and request approval before moving to another phase.
