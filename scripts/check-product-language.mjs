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
  'Event Command',
  'Recruitment Hall',
  'Glory Ledger',
  'Realm Settings',
  'Alliance Command',
  'Kingdom Command',
  'Realm seal',
  'realm is offline',
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
  'sworn to an Alliance',
];

const architecturePhrases = [
  'authority context',
  'read model',
  'read-model',
  'projection layer',
  'context owner',
  'bounded retry cycle',
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

const englishCatalogues = walk(
  path.join(root, 'resources/js/localization/messages'),
  (file) => file.endsWith(`${path.sep}en.ts`),
);

const userInterfaceFiles = [
  ...walk(path.join(root, 'resources/js/pages'), (file) => file.endsWith('.vue')),
  ...walk(path.join(root, 'resources/js/layouts'), (file) => file.endsWith('.vue')),
  ...walk(path.join(root, 'resources/js/components'), (file) => file.endsWith('.vue')),
];

const failures = [];

for (const file of [...englishCatalogues, ...userInterfaceFiles]) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');

  for (const phrase of retiredPhrases) {
    if (source.includes(phrase)) {
      failures.push(`${relative}: retired product phrase "${phrase}"`);
    }
  }

  const isTechnicalAdminCatalogue =
    relative.endsWith('/platform/en.ts') || relative.endsWith('/integrations/en.ts');

  if (!isTechnicalAdminCatalogue) {
    const lower = source.toLowerCase();
    for (const phrase of architecturePhrases) {
      if (lower.includes(phrase.toLowerCase())) {
        failures.push(`${relative}: architecture phrase "${phrase}" is user-facing`);
      }
    }
  }
}

if (failures.length > 0) {
  console.error('KingShot product-language check failed:\n');
  for (const failure of failures) console.error(`- ${failure}`);
  console.error('\nSee docs/product/terminology.md for the product-language contract.');
  process.exit(1);
}

console.log(
  `KingShot product-language check passed (${englishCatalogues.length} English catalogues, ${userInterfaceFiles.length} UI files).`,
);
