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
