import { readFileSync, readdirSync } from 'node:fs';
import { extname, join } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const controllerRoot = fileURLToPath(new URL('../app/Contexts/', import.meta.url));
const cataloguePath = new URL('../resources/js/localization/messages/core/en.ts', import.meta.url);
const allianceExpansionCataloguePath = new URL(
  '../resources/js/localization/alliance-capability-expansion-labels.ts',
  import.meta.url,
);
const giftCodeWorkspaceCataloguePath = new URL(
  '../resources/js/localization/gift-code-workspace-labels.ts',
  import.meta.url,
);

function filesUnder(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    return entry.isDirectory() ? filesUnder(path) : [path];
  });
}

function receiptArguments(source, filename) {
  const argumentsList = [];
  let cursor = 0;

  while ((cursor = source.indexOf('receipt(', cursor)) !== -1) {
    let index = cursor + 'receipt('.length;
    let depth = 1;
    let quote = null;
    let escaped = false;

    for (; index < source.length && depth > 0; index += 1) {
      const character = source[index];
      if (quote !== null) {
        if (escaped) escaped = false;
        else if (character === '\\') escaped = true;
        else if (character === quote) quote = null;
        continue;
      }

      if (character === "'" || character === '"') quote = character;
      else if (character === '(') depth += 1;
      else if (character === ')') depth -= 1;
    }

    if (depth !== 0) throw new Error(`Unclosed receipt call in ${filename}`);
    argumentsList.push(source.slice(cursor + 'receipt('.length, index - 1));
    cursor = index;
  }

  return argumentsList;
}

function firstArgument(source) {
  let round = 0;
  let square = 0;
  let curly = 0;
  let quote = null;
  let escaped = false;

  for (let index = 0; index < source.length; index += 1) {
    const character = source[index];
    if (quote !== null) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }

    if (character === "'" || character === '"') quote = character;
    else if (character === '(') round += 1;
    else if (character === ')') round -= 1;
    else if (character === '[') square += 1;
    else if (character === ']') square -= 1;
    else if (character === '{') curly += 1;
    else if (character === '}') curly -= 1;
    else if (character === ',' && round === 0 && square === 0 && curly === 0) {
      return source.slice(0, index);
    }
  }

  return source;
}

function receiptSource(source, startMarker, endMarker, filename) {
  const receiptStart = source.indexOf(startMarker);
  const receiptEnd = source.indexOf(endMarker, receiptStart);
  if (receiptStart === -1 || receiptEnd === -1) {
    throw new Error(`Unable to locate the receipt catalogue in ${filename}.`);
  }

  return source.slice(receiptStart, receiptEnd);
}

const usedCodes = new Set();
for (const filename of filesUnder(controllerRoot).filter((file) => extname(file) === '.php')) {
  const source = readFileSync(filename, 'utf8');
  for (const argumentsSource of receiptArguments(source, filename)) {
    for (const match of firstArgument(argumentsSource).matchAll(/'([a-z][a-z0-9]*-[a-z0-9-]+)'/g)) {
      usedCodes.add(match[1]);
    }
  }
}

const catalogue = readFileSync(cataloguePath, 'utf8');
const allianceExpansionCatalogue = readFileSync(allianceExpansionCataloguePath, 'utf8');
const giftCodeWorkspaceCatalogue = readFileSync(giftCodeWorkspaceCataloguePath, 'utf8');
const receiptSources = [
  receiptSource(catalogue, '  receipts: {', '\n  },\n  navigation:', 'core/en.ts'),
  receiptSource(
    allianceExpansionCatalogue,
    '  receipts: {',
    '\n  },\n  allianceExpansion:',
    'alliance-capability-expansion-labels.ts',
  ),
  receiptSource(
    giftCodeWorkspaceCatalogue,
    '  receipts: {',
    '\n  },\n};',
    'gift-code-workspace-labels.ts',
  ),
];

const translatedCodes = new Set(['completed']);
for (const source of receiptSources) {
  for (const match of source.matchAll(/\n\s+'([a-z][a-z0-9-]{2,119})':/g)) {
    translatedCodes.add(match[1]);
  }
}

const missing = [...usedCodes].filter((code) => !translatedCodes.has(code)).sort();
const stale = [...translatedCodes]
  .filter((code) => code !== 'completed' && !usedCodes.has(code))
  .sort();

if (missing.length > 0 || stale.length > 0) {
  if (missing.length > 0) console.error(`Missing receipt messages: ${missing.join(', ')}`);
  if (stale.length > 0) console.error(`Unused receipt messages: ${stale.join(', ')}`);
  process.exit(1);
}

console.log(`Action receipt coverage passed (${usedCodes.size} codes).`);
