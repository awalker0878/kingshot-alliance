import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const file = 'resources/js/localization/messages/core/en.ts';
const original = readFileSync(file, 'utf8');
const check = () => spawnSync(process.execPath, ['scripts/check-product-language.mjs'], { encoding: 'utf8' });
assert.equal(check().status, 0, 'Existing pinned debt must pass without approving new wording');
try {
  const marker = 'const messages = {';
  assert.equal(original.split(marker).length, 2);
  writeFileSync(file, original.replace(marker, `${marker}\n  unapprovedWording: 'New append-only interface',`));
  let result = check();
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /New append-only interface/);
  writeFileSync(file, original.replace("'replay rejections'", "'new replay rejections'"));
  result = check();
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /structural wording debt text changed/);
  console.log('Product language negative controls passed: new violations and edits to pinned debt both fail.');
} finally {
  writeFileSync(file, original);
}
