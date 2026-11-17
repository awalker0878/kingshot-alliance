import assert from 'node:assert/strict';
import test from 'node:test';
import { riskEntry, riskReasons, stableUnflaggedSample } from '../../../../scripts/lib/localization-risk.mjs';

test('critical server constraints are surfaced even when numbers are written as words', () => {
  assert.ok(riskReasons('A Kingdom can contain at most four characters for the same account.').includes('quantities-and-limits'));
  assert.ok(riskReasons('A plan may contain at most 5000 planned objects.').includes('quantities-and-limits'));
  assert.ok(riskReasons('A blocker summary is required.').includes('state-and-outcome'));
  assert.ok(riskReasons('A Kingdom must retain at least one effective Kingdom Admin.').includes('permission-and-denial'));
});

test('nonflagged sampling is deterministic and scoped per locale', () => {
  const entries = Array.from({ length: 100 }, (_, i) => riskEntry('client', 'core', 'copy.' + i, 'Friendly message ' + i));
  const first = stableUnflaggedSample(entries, 'ar', 12, 'baseline');
  const repeat = stableUnflaggedSample(entries, 'ar', 12, 'baseline');
  assert.deepEqual(first, repeat);
  assert.equal(first.length, 12);
  assert.notDeepEqual(first, stableUnflaggedSample(entries, 'fr', 12, 'baseline'));
  assert.equal(stableUnflaggedSample([riskEntry('server', 'json', 'key', 'Account recovery required')], 'ar').length, 0);
});
