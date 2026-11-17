import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

const original = JSON.parse(readFileSync('.translation-pr3-current/raw-catalogues.json', 'utf8'));
const root = mkdtempSync(path.join(tmpdir(), 'kingshot-pr3-source-mutant-'));
function check(mutated) {
  const file = path.join(root, 'raw-catalogues.json');
  writeFileSync(file, JSON.stringify(mutated, null, 2) + '\n');
  return spawnSync(process.execPath, ['scripts/check-localization-pr3-postmerge-source.mjs'], {
    env: { ...process.env, LOCALIZATION_POSTMERGE_CURRENT_RAW: file },
    encoding: 'utf8',
  });
}
function changeValue(data, domain, locale) {
  const c = structuredClone(data);
  assert.ok(c[domain]?.[locale], 'required existing locale for negative control');
  c[domain][locale].__unapprovedPr3Mutation = 'UNAPPROVED';
  return c;
}
try {
  const clean = spawnSync(process.execPath, ['scripts/check-localization-pr3-postmerge-source.mjs'], { encoding: 'utf8' });
  assert.equal(clean.status, 0, 'approved merged source must pass: ' + clean.stderr);
  const unexpected = check(changeValue(original, 'events', 'fr'));
  assert.notEqual(unexpected.status, 0, 'unapproved events:fr source edit must fail');
  assert.match(unexpected.stderr, /Unexpected raw source domain\/locale changes/, 'negative control must specifically trigger domain/locale allowlist');
  const changedApproved = check(changeValue(original, 'account', 'en'));
  assert.notEqual(changedApproved.status, 0, 'modified approved account:en source must also fail');
  assert.match(changedApproved.stderr, /structural source hash differs/, 'approved-domain edits still require exact known hash');
  console.log('PR3 POSTMERGE RAW MUTATIONS REJECTED: unapproved events:fr and modified approved account:en');
} finally {
  rmSync(root, { recursive: true, force: true });
}
