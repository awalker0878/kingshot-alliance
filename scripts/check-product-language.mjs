import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

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

function quotedStrings(source) {
  const values = [];

  for (let index = 0; index < source.length; index += 1) {
    const quote = source[index];
    if (quote !== "'" && quote !== '"' && quote !== '`') continue;

    let value = '';
    let escaped = false;
    index += 1;

    for (; index < source.length; index += 1) {
      const character = source[index];
      if (escaped) {
        value += character;
        escaped = false;
        continue;
      }
      if (character === '\\') {
        escaped = true;
        continue;
      }
      if (character === quote) break;
      value += character;
    }

    values.push(value);
  }

  return values;
}

function vueText(source) {
  const values = [];
  for (const match of source.matchAll(/>([^<{][^<]*)</g)) {
    const value = match[1].replace(/\s+/g, ' ').trim();
    if (value !== '') values.push(value);
  }
  return values;
}

function productStrings(file, source) {
  const values = quotedStrings(source);
  if (file.endsWith('.vue')) values.push(...vueText(source));
  return values;
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

for (const file of [...englishCatalogues, ...userInterfaceFiles]) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');
  const strings = productStrings(file, source);
  const isTechnicalSurface =
    relative.endsWith('/platform/en.ts') || relative.endsWith('/integrations/en.ts');

  for (const value of strings) {
    const lower = value.toLowerCase();

    for (const phrase of retiredPhrases) {
      if (lower.includes(phrase.toLowerCase())) {
        failures.push(`${relative}: retired product phrase "${phrase}" in "${value}"`);
      }
    }

    if (!isTechnicalSurface) {
      for (const phrase of architecturePhrases) {
        if (lower.includes(phrase.toLowerCase())) {
          failures.push(`${relative}: architecture phrase "${phrase}" is user-facing in "${value}"`);
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
