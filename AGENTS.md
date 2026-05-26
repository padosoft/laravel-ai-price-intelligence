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
5. Record every Copilot/CI learning in `docs/LESSON.md`.
6. **AUTO-MERGE & ADVANCE (authorized)**: once CI is fully green AND the Copilot review has no
   remaining comments, you are authorized to squash-merge the PR (`gh pr merge <n> --squash
   --delete-branch`), sync local `main`, mark the phase done, and **immediately start the next phase**
   — repeating this whole loop automatically until the roadmap is 100% complete. No need to ask.
- CI green alone is NOT enough; Copilot review must be clean too before merging.

## Final task of the build
Review `docs/LESSON.md` and all knowhow gained, then **create/strengthen** the repo's `AGENTS.md`,
`.claude/rules/`, and any skills with the new knowledge so it persists for future work.

## Distilled lessons (carry into future work)
- **Shell for php/composer/phpunit/pint/phpstan**: on the Windows Herd dev box these tools are on the
  PowerShell PATH but not the bash PATH, so use PowerShell there. On Linux/CI (and any setup where `php`
  is on the bash PATH and `pwsh` may be absent), use bash. Pick whichever shell actually resolves `php`.
- **Local Copilot review**: `copilot --autopilot --yolo -p "/review the changes on this branch vs
  origin/main (git diff origin/main...HEAD); list concrete bugs; reply 'NO ISSUES' if none."` — Premium,
  a few minutes; it even runs code to verify. Run to NO ISSUES before pushing.
- **GitHub Copilot review**: request via REST `gh api --method POST repos/<o>/<r>/pulls/<n>/requested_reviewers
  -f "reviewers[]=copilot-pull-request-reviewer[bot]"` (`gh pr edit --add-reviewer copilot` fails).
- **Reviewers can be wrong / contradict each other** (e.g. PHPStan says a `?? []` is dead while Copilot
  asks to add it). Verify against language/framework semantics; when right, push back with a clarifying
  comment instead of churning the code. Don't add `?? []`/casts/baselines just to silence a tool.
- **PHPStan (level 5 + larastan)**: needs `--memory-limit=1G`. Type Eloquent relation magic with
  `@property-read`. Don't `(bool) config()` for flags — use `Support\Config\Flag::enabled()` (handles
  'auto' and falsy strings). Config closures break `config:cache` → use container bindings or class-strings.
- **Pint** normalizes CRLF→LF and import order; run `pint` then `pint --test` before committing.
- **Orchestra TestCase** is required for any test touching Eloquent (booting the app); its `seed()` method
  is reserved — don't define a private `seed()` helper.
- **GitHub flakiness**: `gh pr merge` can 504 without merging — re-check PR `state` and retry idempotently.
- **Admin-driven backfills (v1.6/v1.7)**: small endpoints added so the admin panel stays complete
  (no dead buttons) and scales — keep them consistent with siblings (`/facets/brands` mirrors
  `/facets/hosts`; anomaly ack
  mirrors alert ack). "Acknowledge"-style writes should be **idempotent + race-safe**: a single atomic
  `whereNull(...)->update([...])` (not read-then-`save()`), scoped to the row's own tenant via
  `withoutTenantScope()` + explicit `tenant_id` so it's correct off the ambient `TenantContext` (jobs);
  bump `updated_at` explicitly when using the query builder; bound bulk-id arrays (`max`, `min:1`,
  `distinct`) and add a cross-tenant isolation test. Note Eloquent builder `->update()` *does* set
  `updated_at` — don't claim otherwise in comments.
- **Facet/aggregate endpoints** are the scale story: compute counts in SQL (`GROUP BY`) or a lazy
  `cursor()`, never page-1; document cost honestly (one DB-side pass, not "constant").
