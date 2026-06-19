# docmd-docs

Use this skill when editing the `docs-site` documentation for `laravel-ai-price-intelligence`.

## Rules

- Keep the docs site static and Markdown-only.
- Put content under `docs-site/docs` and include every page in `docs-site/docmd.config.json` navigation.
- Use docmd containers such as `callout`, `tabs`, `steps`, `collapsible`, `grids`, `grid`, and `card`.
- Do not use MDX, JSX, raw HTML, or `::: button`.
- Run `npm run check` and `npm run build` from `docs-site` before committing docs changes.
- Keep `.docmd-search/config.json` committed and ignore generated search indexes.
