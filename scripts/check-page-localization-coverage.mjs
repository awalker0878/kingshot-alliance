import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const root = process.cwd();
const pagesDirectory = path.join(root, 'resources/js/pages');
const literalAllowList = new Set(['API', 'CSV', 'UTC', 'R1', 'R2', 'R3', 'R4', 'R5']);

function vueFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) return vueFiles(target);
    return entry.isFile() && entry.name.endsWith('.vue') ? [target] : [];
  });
}

function templateSource(source) {
  const start = source.indexOf('<template');
  const open = start === -1 ? -1 : source.indexOf('>', start);
  const end = source.lastIndexOf('</template>');
  if (open === -1 || end === -1 || end <= open) return '';
  return source.slice(open + 1, end);
}

function scriptSource(source) {
  const match = source.match(/<script\b[^>]*>([\s\S]*?)<\/script>/);
  return match?.[1] ?? '';
}

function normalized(value) {
  return value
    .replace(/&(?:nbsp|middot|mdash|ndash);/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function isVisibleLanguage(value) {
  const text = normalized(value);
  if (text === '' || literalAllowList.has(text)) return false;
  if (/^R[1-5](?:\s*[-–/]\s*R[1-5])*$/.test(text)) return false;
  return /[A-Za-z]{2,}/.test(text);
}

function rawTemplateStrings(template) {
  const values = [];
  const withoutExpressions = template.replace(/{{[\s\S]*?}}/g, '');

  for (const match of withoutExpressions.matchAll(/>([^<]+)</g)) {
    const value = normalized(match[1]);
    if (isVisibleLanguage(value)) values.push(value);
  }

  const names = 'title|subtitle|eyebrow|placeholder|aria-label|alt|label|description';
  for (const match of template.matchAll(new RegExp(`(?<![:@\\w-])(?:${names})\\s*=\\s*["']([^"']+)["']`, 'g'))) {
    const value = normalized(match[1]);
    if (isVisibleLanguage(value)) values.push(value);
  }

  for (const match of template.matchAll(new RegExp(`:(?:${names})\\s*=\\s*"([^"]+)"`, 'g'))) {
    const expression = match[1];
    if (/\bt\s*\(/.test(expression)) continue;
    for (const literal of expression.matchAll(/['`]([^'`]*[A-Za-z][^'`]*)['`]/g)) {
      const value = normalized(literal[1].replace(/\$\{[^}]+}/g, ''));
      if (isVisibleLanguage(value)) values.push(value);
    }
  }

  return values;
}

function rawScriptLabels(sourceText) {
  const source = ts.createSourceFile('page.ts', sourceText, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  const values = [];
  const visiblePropertyNames = new Set([
    'label',
    'title',
    'subtitle',
    'eyebrow',
    'description',
    'placeholder',
    'helpText',
  ]);

  function visit(node) {
    if (ts.isPropertyAssignment(node)) {
      const name = node.name.text ?? node.name.getText(source).replace(/^['"]|['"]$/g, '');
      const initializer = node.initializer;
      if (
        visiblePropertyNames.has(name) &&
        (ts.isStringLiteral(initializer) || ts.isNoSubstitutionTemplateLiteral(initializer)) &&
        isVisibleLanguage(initializer.text)
      ) {
        values.push(initializer.text);
      }
    }
    ts.forEachChild(node, visit);
  }

  visit(source);
  return values;
}

const failures = [];
const files = vueFiles(pagesDirectory);

for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');

  if (!source.includes("from '@/localization'")) {
    failures.push(`${relative}: missing localization import`);
    continue;
  }

  if (!/\buseLocale\(\)/.test(source)) {
    failures.push(`${relative}: missing useLocale() call`);
  }

  const rawStrings = [
    ...rawTemplateStrings(templateSource(source)),
    ...rawScriptLabels(scriptSource(source)),
  ];
  for (const value of [...new Set(rawStrings)]) {
    failures.push(`${relative}: visible copy must use localization: "${value}"`);
  }
}

if (failures.length > 0) {
  console.error('Every Inertia page must use localized visible copy:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log(`Page localization coverage: ${files.length} Vue pages checked with no raw visible copy.`);
