import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const target = 'resources/js/localization/messages/transfers/en.ts';
const original = readFileSync(target, 'utf8');
const before = "transferEvidenceTitle: 'In-game evidence',";
const after = "transferEvidenceTitle: 'In-game evidence!',";
assert.equal(original.split(before).length - 1, 1, 'must mutate exactly one effective English message');
const directory = mkdtempSync(path.join(process.cwd(), '.translation-pr3-mutant-'));
try {
  writeFileSync(target, original.replace(before, after));
  const generated = spawnSync(process.execPath, ['scripts/prepare-localization-baseline.mjs', directory, '--current'], { encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 });
  assert.equal(generated.status, 0, 'mutant exporter must succeed: ' + generated.stderr);
  const failed = spawnSync(process.execPath, ['scripts/check-localization-pr3-parity.mjs', directory], { encoding: 'utf8' });
  assert.notEqual(failed.status, 0, 'a one-character effective English change must fail parity');
  assert.match(failed.stderr, /PR3 EFFECTIVE DIFF: effective-en\.json/);
  assert.doesNotMatch(failed.stderr, /MISSING/, 'a hash mismatch, not an absent export, must cause failure');
  console.log('PR3 MUTATION REJECTED: one-character English Transfer Evidence change caused effective-en hash mismatch (exit=' + failed.status + ')');
} finally {
  writeFileSync(target, original);
  rmSync(directory, { recursive: true, force: true });
}
