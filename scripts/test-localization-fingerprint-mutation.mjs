import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

const inputFile = 'semantic-risk-inventory.json';
const manifest = JSON.parse(
  readFileSync('resources/data/localization/baseline-fingerprints.json', 'utf8'),
);
const root = mkdtempSync(path.join(tmpdir(), 'kingshot-fingerprint-mutation-'));

function verify() {
  return spawnSync(process.execPath, [
    'scripts/check-localization-baseline-fingerprints.mjs',
    '--pinned-only',
  ], {
    env: { ...process.env, LOCALIZATION_FINGERPRINT_PINNED_DIR: root },
    encoding: 'utf8',
  });
}

try {
  // Start from the real pinned export, not manufactured fixture data.
  for (const name of Object.keys(manifest.pinned)) {
    copyFileSync(path.join('.translation-pr1-baseline', name), path.join(root, name));
  }
  const clean = verify();
  assert.equal(clean.status, 0, 'unmodified pinned baseline must pass: ' + clean.stderr);

  const file = path.join(root, inputFile);
  const source = readFileSync(file);
  assert.ok(source.length > 1, 'risk inventory must have nonempty contents');
  // One-byte mutation: the first byte changes, all other bytes stay identical.
  const mutated = Buffer.from(source);
  mutated[0] = mutated[0] === 123 ? 91 : 123;
  writeFileSync(file, mutated);
  assert.equal(mutated.length, source.length, 'mutation must preserve byte length');
  let changed = 0;
  for (let i = 0; i < source.length; i++) {
    if (source[i] !== mutated[i]) changed++;
  }
  assert.equal(changed, 1, 'exactly one byte must be altered');

  const rejected = verify();
  assert.notEqual(rejected.status, 0, 'fingerprint must reject a one-byte change');
  assert.match(rejected.stderr, /MISMATCH pinned\/semantic-risk-inventory\.json/);
  assert.doesNotMatch(rejected.stderr, /MISSING /, 'the rejection must not be a missing-file error');
  console.log('FINGERPRINT MUTATION REJECTED: pinned semantic-risk-inventory.json (one byte changed, exit=' + rejected.status + ')');
} finally {
  rmSync(root, { recursive: true, force: true });
}
