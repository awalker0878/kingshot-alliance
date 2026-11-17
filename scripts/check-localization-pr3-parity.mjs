import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const manifest = JSON.parse(readFileSync('resources/data/localization/baseline-fingerprints.json', 'utf8'));
assert.equal(manifest.baselineRef, '7cf387b628bbbaeb81d10120340e559b46403bee');
const directory = process.argv[2] ?? '.translation-pr3-current';
const checked = [
  'effective-all-locales.json',
  'effective-en.json',
  'server-catalogues.json',
  'server-php-catalogues.json',
  'server-php-hashes.json',
  'semantic-risk-inventory.json',
];
let failures = 0;
for (const filename of checked) {
  const expected = manifest.corrected[filename];
  if (!expected) throw new Error('Missing corrected baseline fingerprint for ' + filename);
  let actual = 'MISSING';
  try {
    actual = createHash('sha256').update(readFileSync(path.join(directory, filename))).digest('hex');
  } catch {
    // A missing export is a hard failure, not a zero-diff claim.
  }
  if (actual !== expected) {
    console.error('PR3 EFFECTIVE DIFF: ' + filename + ': expected ' + expected + ', actual ' + actual);
    failures++;
  } else {
    console.log('PR3 ZERO DIFF: ' + filename + ' sha256=' + actual);
  }
}
if (failures > 0) process.exitCode = 1;
else console.log('PR3 ZERO EFFECTIVE CATALOGUE DIFF: ' + checked.length + ' pinned effective/server/risk outputs');
