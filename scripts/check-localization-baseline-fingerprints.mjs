import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const manifest = JSON.parse(readFileSync('resources/data/localization/baseline-fingerprints.json', 'utf8'));
const pinnedOnly = process.argv.includes('--pinned-only');
let failed = 0;
for (const [label, files] of Object.entries({
  pinned: manifest.pinned,
  ...(pinnedOnly ? {} : { corrected: manifest.corrected }),
})) {
  const root = label === 'pinned'
    ? (process.env.LOCALIZATION_FINGERPRINT_PINNED_DIR ?? '.translation-pr1-baseline')
    : (process.env.LOCALIZATION_FINGERPRINT_CORRECTED_DIR ?? '.translation-pr1-corrected');
  for (const [name, expected] of Object.entries(files)) {
    let actual;
    try {
      actual = createHash('sha256').update(readFileSync(path.join(root, name))).digest('hex');
    } catch (error) {
      console.error('MISSING ' + label + '/' + name + ': ' + String(error));
      failed++;
      continue;
    }
    if (actual !== expected) {
      console.error('MISMATCH ' + label + '/' + name + ': expected ' + expected + ', actual ' + actual);
      failed++;
    }
  }
}
if (failed > 0) {
  console.error('Immutable localization baseline fingerprint verification failed: ' + failed);
  process.exitCode = 1;
} else {
  console.log('Immutable localization baseline fingerprint verification passed: ' +
    (Object.keys(manifest.pinned).length + (pinnedOnly ? 0 : Object.keys(manifest.corrected).length)) + ' outputs');
}
