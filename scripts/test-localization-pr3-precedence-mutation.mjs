import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const target = 'resources/js/localization/messages/account/en.ts';
const original = readFileSync(target, 'utf8');
const correct = 'export default mergeCatalogue(messages, notificationOverlay);';
const wrong = 'export default mergeCatalogue(notificationOverlay, messages);';
assert.equal(original.split(correct).length - 1, 1, 'production merge order must be unambiguous');
const directory = mkdtempSync(path.join(process.cwd(), '.translation-pr3-wrong-order-'));
try {
  // Deliberate precedence mutant: old raw values replace the explicitly newer overlay.
  writeFileSync(target, original.replace(correct, wrong));
  const exported = spawnSync(process.execPath, ['scripts/prepare-localization-baseline.mjs', directory, '--current'], {
    encoding: 'utf8', maxBuffer: 20 * 1024 * 1024,
  });
  assert.equal(exported.status, 0, 'mutant must export normally: ' + exported.stderr);
  const account = JSON.parse(readFileSync(path.join(directory, 'effective-all-locales.json'), 'utf8')).account.en;
  assert.equal(account['notifications.eyebrow'], 'Governor notifications', 'the broken merge must replace the published winner');
  const gate = spawnSync(process.execPath, ['scripts/check-localization-pr3-parity.mjs', directory], { encoding: 'utf8' });
  assert.notEqual(gate.status, 0, 'reversed overlay precedence must fail complete effective parity');
  assert.match(gate.stderr, /PR3 EFFECTIVE DIFF: effective-en\.json/);
  assert.doesNotMatch(gate.stderr, /MISSING/, 'must detect a real value mismatch');
  console.log('PR3 PRECEDENCE MUTATION REJECTED: notifications.eyebrow reverted from Recipient delivery to Governor notifications; effective-en mismatch detected');
} finally {
  writeFileSync(target, original);
  rmSync(directory, { recursive: true, force: true });
}
