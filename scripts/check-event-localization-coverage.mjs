import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const ts = require('typescript');

const root = process.cwd();
const eventDirectory = path.join(root, 'resources/js/localization/messages/events');
const supportedLocales = [
  'en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW',
];
const minimumLocalizedKeys = 89;
const requiredHistoryKeys = [
  'events.history.title',
  'events.history.subtitle',
  'events.history.from',
  'events.history.until',
  'events.history.rows',
  'events.history.applyFilters',
  'events.history.clearFilters',
  'events.history.metricTrends',
  'events.history.metricTrendsHelp',
  'events.history.average',
  'events.history.best',
  'events.history.samples',
  'events.history.latest',
  'events.history.leaderboards',
  'events.history.leaderboardsHelp',
  'events.history.noEvents',
  'events.history.noEvidence',
  'events.history.evidenceAria',
  'events.history.rallyAssignments',
  'events.history.assignments',
  'events.history.allianceResults',
  'events.history.participants',
  'events.history.governorCount',
  'events.history.representedAlliance',
  'events.history.noParticipants',
];

function unwrap(node) {
  let current = node;
  while (
    current &&
    (ts.isSatisfiesExpression(current) || ts.isAsExpression(current) || ts.isParenthesizedExpression(current))
  ) {
    current = current.expression;
  }
  return current;
}

function flattenedKeys(file) {
  const sourceText = fs.readFileSync(file, 'utf8');
  const source = ts.createSourceFile(file, sourceText, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  if (source.parseDiagnostics.length > 0) {
    const diagnostics = source.parseDiagnostics
      .map((diagnostic) => ts.flattenDiagnosticMessageText(diagnostic.messageText, ' '))
      .join('; ');
    throw new Error(`${path.relative(root, file)} cannot be parsed: ${diagnostics}`);
  }

  let messages = null;
  source.forEachChild((node) => {
    if (!ts.isVariableStatement(node)) return;
    for (const declaration of node.declarationList.declarations) {
      if (declaration.name.getText(source) === 'messages') {
        messages = unwrap(declaration.initializer);
      }
    }
  });

  if (!messages || !ts.isObjectLiteralExpression(messages)) {
    throw new Error(`${path.relative(root, file)} must declare a messages object.`);
  }

  const keys = new Set();
  function visit(node, prefix = '') {
    const object = unwrap(node);
    if (!object || !ts.isObjectLiteralExpression(object)) return;

    for (const property of object.properties) {
      if (!ts.isPropertyAssignment(property)) continue;
      const name = property.name.text ?? property.name.getText(source).replace(/^['"]|['"]$/g, '');
      const key = prefix === '' ? name : `${prefix}.${name}`;
      const value = unwrap(property.initializer);
      if (value && ts.isObjectLiteralExpression(value)) {
        visit(value, key);
      } else {
        keys.add(key);
      }
    }
  }

  visit(messages);
  return keys;
}

const files = new Map();
for (const locale of supportedLocales) {
  const file = path.join(eventDirectory, `${locale}.ts`);
  if (!fs.existsSync(file)) {
    throw new Error(`Missing Event localization catalogue: ${locale}`);
  }
  files.set(locale, flattenedKeys(file));
}

const english = files.get('en');
if (!english || english.size === 0) {
  throw new Error('English Event localization catalogue is empty.');
}

let failed = false;
for (const locale of supportedLocales.filter((value) => value !== 'en')) {
  const localized = files.get(locale);
  const unknown = [...localized].filter((key) => !english.has(key));
  const coverage = ((localized.size / english.size) * 100).toFixed(1);
  console.log(`${locale}: ${localized.size}/${english.size} source keys (${coverage}%), English fallback for ${english.size - localized.size}`);

  if (unknown.length > 0) {
    failed = true;
    console.error(`  Unknown keys: ${unknown.join(', ')}`);
  }
  if (localized.size < minimumLocalizedKeys) {
    failed = true;
    console.error(`  Coverage regression: expected at least ${minimumLocalizedKeys} localized Event keys.`);
  }

  const missingHistory = requiredHistoryKeys.filter((key) => !localized.has(key));
  if (missingHistory.length > 0) {
    failed = true;
    console.error(`  Missing Event History translations: ${missingHistory.join(', ')}`);
  }
}

if (failed) process.exit(1);
