# Claude / Agent Instructions

Follow `AGENTS.md`. **Read `docs/LESSON.md` and `docs/PROGRESS.md` before making changes**, and keep
them updated as you work (PROGRESS.md after every step; LESSON.md whenever you learn something or get
Copilot/CI feedback).

Use PowerShell for `php` / `composer` / `vendor\bin\phpunit` (not on bash PATH).

No task is complete until `composer validate`, PHPUnit, PHPStan (when configured) and Pint (when
configured) pass locally and in GitHub Actions.

For PRs, request GitHub Copilot Code Review and wait for it before merge; record learnings in
`docs/LESSON.md`.

When spawning parallel subagents, pass the contents of `docs/LESSON.md` into their context.
