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

## PRs & Copilot review loop
- Prefer small, focused PRs after the bootstrap.
- Request **GitHub Copilot Code Review** and wait for it; CI green alone is not enough.
- Fix or explicitly resolve all actionable Copilot feedback before merge, and **record what you
  learned in `docs/LESSON.md`**.

## Final task of the build
Review `docs/LESSON.md` and all knowhow gained, then **create/strengthen** the repo's `AGENTS.md`,
`.claude/rules/`, and any skills with the new knowledge so it persists for future work.
