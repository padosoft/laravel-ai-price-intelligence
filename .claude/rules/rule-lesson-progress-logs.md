# Rule: LESSON.md & PROGRESS.md discipline

- Before changing code, read `docs/LESSON.md` and `docs/PROGRESS.md`.
- Update `docs/PROGRESS.md` after every meaningful step so an interrupted session can resume cold.
- Append to `docs/LESSON.md` whenever you learn something non-obvious, fix a bug, hit an environment
  quirk, or receive Copilot/CI feedback.
- When spawning a parallel subagent, pass `docs/LESSON.md` (and the relevant PROGRESS.md section)
  into its prompt context.
- Use PowerShell for php/composer/phpunit (not on bash PATH).
- Definition of done: composer validate + PHPUnit + PHPStan (when configured) + Pint (when
  configured), locally and in CI.
- PRs: request GitHub Copilot Code Review, wait for it, resolve actionable feedback, then record
  learnings in `docs/LESSON.md`.
- As the final build task, consolidate LESSON.md knowhow into AGENTS.md, .claude/rules and skills.

## STRICT per-phase delivery loop (mandatory)
For EVERY roadmap phase, in order:
1. Implement the phase.
2. **Local loop until clean**: run `vendor\bin\phpunit` AND the local `copilot` CLI review; fix every
   issue; repeat until both are clean. Do NOT push before local is clean.
3. Commit on a per-phase branch (`feat/phase-N-...`) — one PR per phase.
4. Push and open/update the PR; request GitHub Copilot review (REST:
   `gh api --method POST repos/<o>/<r>/pulls/<n>/requested_reviewers -f "reviewers[]=copilot-pull-request-reviewer[bot]"`).
5. **Remote loop until green**: wait for CI to pass AND for GitHub Copilot review to have zero
   actionable comments. Fix → push → re-check, looping until both are satisfied.
6. Record every Copilot/CI learning in `docs/LESSON.md`.
7. **AUTO-MERGE & ADVANCE (authorized, no need to ask)**: when CI is fully green AND the Copilot
   review has zero remaining comments, squash-merge the PR (`gh pr merge <n> --squash --delete-branch`),
   sync local `main`, mark the phase done, and immediately start the next phase — repeating until the
   roadmap is 100% complete.
