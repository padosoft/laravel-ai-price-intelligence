# AGENTS.md — working agreement for this repository

Rules every agent/session (and every spawned subagent) MUST follow when working on
`padosoft/laravel-ai-price-intelligence`.

## Before you change anything
1. **Read `docs/LESSON.md`** — accumulated gotchas and non-obvious facts. Honor them.
2. **Read `docs/PROGRESS.md`** — current build state and the next action. Resume from there.
3. The full design lives in `docs/PROJECT.md` (core) and the admin repo's `docs/TEMPLATE.md` /
   `docs/IMPLEMENTATION.md`.

## While you work
- **Keep `docs/PROGRESS.md` current** after every meaningful step (phase done, file added, blocker).
  A session can be interrupted at any moment — PROGRESS.md must let the next session resume cold.
- **Append to `docs/LESSON.md`** whenever you: learn something non-obvious, fix a bug, hit an
  environment quirk, or receive **Copilot/CI feedback**. Lessons prevent repeating mistakes.
- When you spawn a parallel subagent, **pass the contents of `docs/LESSON.md`** (and the relevant
  PROGRESS.md section) into its prompt context.

## Tooling
- Use **PowerShell** for `php`, `composer`, `vendor\bin\phpunit` (PHP/Composer are not on bash PATH).
- Test DB is SQLite `:memory:`. Run `vendor\bin\phpunit` before claiming a phase done.

## Code conventions
- `declare(strict_types=1)`, `final` classes, readonly DTOs with `fromArray()/toArray()`.
- Table names from `config('price-intelligence.tables.*')`; migrations idempotent
  (`Schema::hasTable` guard).
- Everything pluggable via Interface + Driver. Optional deps wired via null-object (no hard require).
- Tenant isolation via `BelongsToTenant`; never on pre-auth lookups (e.g. ApiKey).

## Definition of done (per phase / PR)
No task is complete until, locally and in CI:
`composer validate`, **PHPUnit**, PHPStan (when configured), Pint (when configured) all pass.

## PRs & Copilot review loop — STRICT, one PR per phase
For EVERY roadmap phase, in this exact order (non-negotiable):
1. Implement the phase on a per-phase branch (`feat/phase-N-...`).
2. **Local loop until clean**: run `vendor\bin\phpunit` AND the local `copilot` CLI review; fix every
   finding; repeat until both are clean. Never push before local is clean.
3. Push and open/update the PR (one PR per phase), then request GitHub Copilot review via REST:
   `gh api --method POST repos/<owner>/<repo>/pulls/<n>/requested_reviewers -f "reviewers[]=copilot-pull-request-reviewer[bot]"`
   (`gh pr edit --add-reviewer copilot` and GraphQL `userLogins` both fail — REST works).
4. **Remote loop until green**: wait for CI to pass AND for the GitHub Copilot review to have **zero
   actionable comments**. Fix → push → re-check, looping until both are satisfied.
5. Record every Copilot/CI learning in `docs/LESSON.md`. Only then mark the phase done.
- CI green alone is NOT enough; Copilot review must be clean too.

## Final task of the build
Review `docs/LESSON.md` and all knowhow gained, then **create/strengthen** the repo's `AGENTS.md`,
`.claude/rules/`, and any skills with the new knowledge so it persists for future work.
