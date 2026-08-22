import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const localeCodes = [
  'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW',
];
const root = new URL('../resources/js/localization/messages/territory/', import.meta.url);
const english = (await import(new URL('en.ts', root))).default.territory;

function leafPaths(value, prefix = '') {
  const result = [];
  for (const [key, child] of Object.entries(value)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (child && typeof child === 'object' && !Array.isArray(child)) {
      result.push(...leafPaths(child, path));
    } else {
      result.push(path);
    }
  }
  return result.sort();
}

const expected = leafPaths(english);
for (const locale of localeCodes) {
  const url = new URL(`${locale}.ts`, root);
  const source = await readFile(fileURLToPath(url), 'utf8');
  assert.doesNotMatch(source, /from\s+['"]\.\/en['"]/, `${locale} must not alias the English Territory catalogue`);
  const catalogue = (await import(url)).default.territory;
  assert.deepStrictEqual(leafPaths(catalogue), expected, `${locale} Territory keys drifted from English`);
}

console.log(`Territory localization: ${localeCodes.length} native catalogues match the English key contract.`);
