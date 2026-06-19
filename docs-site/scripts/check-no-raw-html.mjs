import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { extname, join } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
const docsDir = join(root, 'docs');
const siteDir = join(root, '_site');
const failures = [];
const rawHtml = /<\/?[a-z][\w:-]*(\s|>|\/>)/i;

function walk(dir, predicate, visit) {
  if (!existsSync(dir)) return;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(path, predicate, visit);
      continue;
    }
    if (predicate(path)) visit(path);
  }
}

walk(docsDir, path => extname(path) === '.md', path => {
  const text = readFileSync(path, 'utf8');
  if (rawHtml.test(text)) failures.push(`${path}: raw HTML is not allowed in docs Markdown`);
  if (text.includes('::: button')) failures.push(`${path}: ::: button is not allowed`);
});

walk(siteDir, path => extname(path) === '.html', path => {
  const text = readFileSync(path, 'utf8');
  if (text.includes(':::')) failures.push(`${path}: leaked docmd container marker`);
});

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log('Markdown and generated HTML guard passed.');
