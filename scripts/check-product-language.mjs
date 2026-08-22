import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const root = process.cwd();

const retiredPhrases = [
  'Realm Gate',
  'Enter the Realm',
  'Leave the Realm',
  'Begin as a Governor',
  'Alliance Hall',
  'Alliance Summons',
  'Event Command',
  'Recruitment Hall',
  'Glory Ledger',
  'Realm Settings',
  'Alliance Command',
  'Kingdom Command',
  'Royal Court',
  "King's Court",
  'Court Auto-Assign',
  'Court watch',
  'King Perk',
  'Realm seal',
  'Realm guard',
  'realm key',
  'realm is offline',
  'command hall',
  'command surface',
  'command rooms',
  'Alliance rooms',
  'Event Codex',
  'Event pattern',
  'Event patterns',
  'Call saved Event',
  'Rally operations',
  'Roster operations',
  'Governor planning intelligence',
  'Raise an Alliance banner',
  'raise a new Alliance banner',
  'Realm time zone',
  'Realm languages',
  'realm safety check',
  'Citadel Warden',
  'Citadel decree',
  'Alliance charter',
  'Alliance fleet',
  'sworn to an Alliance',
];

const architecturePhrases = [
  'authority context',
  'read model',
  'read-model',
  'projection layer',
  'context owner',
  'bounded retry cycle',
  'bounded polling',
  'planning mutations',
  'append-only',
  'chronicle',
  'provenance',
  'persistence',
  'idempotent',
  'quarantined',
  'staged',
  'replay',
];

const retiredPresentationReferences = [
  'Command/Overview',
  'Alliance/Hall',
  'Alliance/Recruitment/Hall',
  'Intelligence/GloryLedger',
  'Citadel/RealmControl',
  'Citadel/EventCodex',
  'Kingdom/RoyalCourt',
  'Operations/Events/Chronicle',
];

function walk(directory, predicate) {
  if (!fs.existsSync(directory)) return [];

  const files = [];
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...walk(absolute, predicate));
    } else if (predicate(absolute)) {
      files.push(absolute);
    }
  }
  return files;
}

function unwrap(node) {
  let current = node;
  while (
    current &&
    (ts.isSatisfiesExpression(current) ||
      ts.isAsExpression(current) ||
      ts.isParenthesizedExpression(current))
  ) {
    current = current.expression;
  }
  return current;
}

function catalogueStrings(file, sourceText) {
  const source = ts.createSourceFile(file, sourceText, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  const values = [];
  let rootObject = null;

  source.forEachChild((node) => {
    if (!ts.isVariableStatement(node)) return;
    for (const declaration of node.declarationList.declarations) {
      if (declaration.name.getText(source) === 'messages') rootObject = unwrap(declaration.initializer);
    }
  });

  function visit(node) {
    const value = unwrap(node);
    if (!value) return;
    if (ts.isStringLiteral(value) || ts.isNoSubstitutionTemplateLiteral(value)) {
      values.push(value.text);
      return;
    }
    if (ts.isTemplateExpression(value)) {
      values.push(
        [value.head.text, ...value.templateSpans.map((span) => span.literal.text)].join(' '),
      );
      return;
    }
    if (ts.isObjectLiteralExpression(value)) {
      for (const property of value.properties) {
        if (ts.isPropertyAssignment(property)) visit(property.initializer);
      }
      return;
    }
    if (ts.isArrayLiteralExpression(value)) {
      for (const element of value.elements) visit(element);
    }
  }

  visit(rootObject);
  return values;
}

function templateSource(source) {
  const start = source.indexOf('<template');
  const open = start === -1 ? -1 : source.indexOf('>', start);
  const end = source.lastIndexOf('</template>');
  if (open === -1 || end === -1 || end <= open) return '';
  return source.slice(open + 1, end);
}

function vueText(template) {
  const withoutExpressions = template.replace(/{{[\s\S]*?}}/g, '');
  const values = [];
  for (const match of withoutExpressions.matchAll(/>([^<]+)</g)) {
    const value = match[1].replace(/\s+/g, ' ').trim();
    if (value !== '') values.push(value);
  }
  return values;
}

function vueAttributeStrings(template) {
  const values = [];
  const names = 'title|subtitle|eyebrow|placeholder|aria-label|alt|label|description';

  for (const match of template.matchAll(new RegExp(`(?<![:@\\w-])(?:${names})\\s*=\\s*["']([^"']+)["']`, 'g'))) {
    values.push(match[1]);
  }

  for (const match of template.matchAll(new RegExp(`:(?:${names})\\s*=\\s*"([^"]+)"`, 'g'))) {
    const expression = match[1];
    if (/\bt\s*\(/.test(expression)) continue;
    for (const literal of expression.matchAll(/['`]([^'`]*[A-Za-z][^'`]*)['`]/g)) {
      values.push(literal[1]);
    }
  }

  return values;
}

function vueStrings(source) {
  const template = templateSource(source);
  return [...vueText(template), ...vueAttributeStrings(template)];
}

function normalizeProductString(value) {
  return value.replace(/{[^{}]+}/g, ' ').replace(/\s+/g, ' ').trim();
}

const englishCatalogues = walk(
  path.join(root, 'resources/js/localization/messages'),
  (file) => file.endsWith(`${path.sep}en.ts`),
);

const userInterfaceFiles = [
  ...walk(path.join(root, 'resources/js/pages'), (file) => file.endsWith('.vue')),
  ...walk(path.join(root, 'resources/js/layouts'), (file) => file.endsWith('.vue')),
  ...walk(path.join(root, 'resources/js/components'), (file) => file.endsWith('.vue')),
];

const staticProductFiles = [path.join(root, 'public/offline.html')].filter((file) =>
  fs.existsSync(file),
);

const presentationReferenceFiles = [
  ...walk(path.join(root, 'app'), (file) => file.endsWith('.php')),
  ...walk(path.join(root, 'routes'), (file) => file.endsWith('.php')),
  ...walk(path.join(root, 'resources/js'), (file) => /\.(?:ts|vue)$/.test(file)),
  ...walk(path.join(root, 'tests'), (file) => /\.(?:php|ts|js|vue)$/.test(file)),
  ...walk(path.join(root, '.github/workflows'), (file) => /\.ya?ml$/.test(file)),
  ...[
    path.join(root, 'vite.king-perks.config.ts'),
    path.join(root, 'tsconfig.king-perks.json'),
  ].filter((file) => fs.existsSync(file)),
];

const failures = [];

for (const file of englishCatalogues) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');
  const strings = catalogueStrings(file, source);
  const isTechnicalSurface =
    relative.endsWith('/platform/en.ts') || relative.endsWith('/integrations/en.ts');

  for (const rawValue of strings) {
    const value = normalizeProductString(rawValue);
    const lower = value.toLowerCase();

    for (const phrase of retiredPhrases) {
      if (lower.includes(phrase.toLowerCase())) {
        failures.push(`${relative}: retired product phrase "${phrase}" in "${rawValue}"`);
      }
    }

    if (!isTechnicalSurface) {
      for (const phrase of architecturePhrases) {
        if (lower.includes(phrase.toLowerCase())) {
          failures.push(`${relative}: architecture phrase "${phrase}" is user-facing in "${rawValue}"`);
        }
      }
    }
  }
}

for (const file of userInterfaceFiles) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');
  const strings = vueStrings(source);
  const isTechnicalSurface =
    relative.includes('/pages/Platform/') || relative.includes('/pages/Alliance/Connections/');

  for (const rawValue of strings) {
    const value = normalizeProductString(rawValue);
    const lower = value.toLowerCase();

    for (const phrase of retiredPhrases) {
      if (lower.includes(phrase.toLowerCase())) {
        failures.push(`${relative}: retired product phrase "${phrase}" in "${rawValue}"`);
      }
    }

    if (!isTechnicalSurface) {
      for (const phrase of architecturePhrases) {
        if (lower.includes(phrase.toLowerCase())) {
          failures.push(`${relative}: architecture phrase "${phrase}" is user-facing in "${rawValue}"`);
        }
      }
    }
  }
}

for (const file of staticProductFiles) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');
  const lower = source.toLowerCase();

  for (const phrase of [...retiredPhrases, ...architecturePhrases]) {
    if (lower.includes(phrase.toLowerCase())) {
      failures.push(`${relative}: retired or technical product phrase "${phrase}"`);
    }
  }
}

for (const file of presentationReferenceFiles) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');

  for (const reference of retiredPresentationReferences) {
    if (relative.includes(reference) || source.includes(reference)) {
      failures.push(`${relative}: retired presentation reference "${reference}"`);
    }
  }
}

if (failures.length > 0) {
  console.error('KingShot product-language check failed:\n');
  for (const failure of [...new Set(failures)].sort()) console.error(`- ${failure}`);
  console.error('\nSee docs/product/terminology.md for the product-language contract.');
  process.exit(1);
}

console.log(
  `KingShot product-language check passed (${englishCatalogues.length} English catalogues, ${userInterfaceFiles.length} UI files, ${staticProductFiles.length} static files).`,
);
