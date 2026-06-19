# Keep docmd docs in sync

When package APIs, commands, migrations, events, or configuration keys change, update `docs-site/docs` in the same branch.

Required checks:

- `npm run check` from `docs-site`
- `npm run build` from `docs-site`
- Confirm `_site/index.html`, `_site/llms.txt`, `_site/sitemap.xml`, and `_site/.docmd-search/manifest.json` exist after build
