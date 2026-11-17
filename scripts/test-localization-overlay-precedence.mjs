import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const inventory = JSON.parse(readFileSync('.translation-pr3-current/remaining-overlay-inventory.json', 'utf8'));
const changed = inventory.scopes.flatMap(scope => scope.entries
  .filter(entry => entry.kind === 'overridden').map(entry => ({ domain: scope.domain, ...entry })));
assert.equal(changed.length, 4, 'Every differing-value collision must be exercised');
for (const collision of changed) {
  const target = `resources/js/localization/messages/${collision.domain}/en.ts`;
  const source = readFileSync(target, 'utf8');
  const before = `'${collision.winner}'`;
  assert.equal(source.split(before).length - 1, 1, 'Mutate exactly one canonical winner: ' + collision.key);
  const directory = mkdtempSync(path.join(process.cwd(), '.translation-overlay-mutant-'));
  try {
    writeFileSync(target, source.replace(before, `'${collision.previous}'`));
    const exported = spawnSync(process.execPath, ['scripts/prepare-localization-baseline.mjs', directory, '--current'], { encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 });
    assert.equal(exported.status, 0, 'Mutated source must export normally: ' + exported.stderr);
    const values = JSON.parse(readFileSync(path.join(directory, 'effective-all-locales.json'), 'utf8'));
    assert.equal(values[collision.domain].en[collision.key], collision.previous);
    const gate = spawnSync(process.execPath, ['scripts/check-localization-pr3-parity.mjs', directory], { encoding: 'utf8' });
    assert.notEqual(gate.status, 0, 'Reversed precedence must fail: ' + collision.key);
    assert.match(gate.stderr, /PR3 EFFECTIVE DIFF: effective-en\.json/);
    assert.doesNotMatch(gate.stderr, /MISSING/);
    console.log('Rejected actual canonical precedence mutation: ' + collision.key);
  } finally {
    writeFileSync(target, source);
    rmSync(directory, { recursive: true, force: true });
  }
}
