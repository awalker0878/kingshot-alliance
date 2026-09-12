import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import {
  acceptContentDraft,
  reconcileContentDrafts,
} from '../../../../resources/js/features/alliance-content/reconcileDrafts.ts';

test('clean off-page rows are discarded while intentional unsaved edits survive pagination', () => {
  const result = reconcileContentDrafts(
    { a: { title: 'Edited locally' }, b: { title: 'Clean' } },
    { a: { title: 'Original' }, b: { title: 'Clean' } },
    { c: { title: 'Next page' } },
  );
  assert.deepEqual(result.drafts, { a: { title: 'Edited locally' }, c: { title: 'Next page' } });
  assert.deepEqual(result.baseline, { a: { title: 'Original' }, c: { title: 'Next page' } });
});

test('new server state refreshes only clean drafts and never overwrites edited recurrence', () => {
  const result = reconcileContentDrafts(
    { a: { days: [1], time: '12:00' }, b: { days: [2], time: '13:00' } },
    { a: { days: [1], time: '12:00' }, b: { days: [2], time: '11:00' } },
    { a: { days: [3], time: '15:00' }, b: { days: [4], time: '16:00' } },
  );
  assert.deepEqual(result.drafts.a, { days: [3], time: '15:00' });
  assert.deepEqual(result.drafts.b, { days: [2], time: '13:00' });
  result.drafts.a!.days.push(5);
  assert.deepEqual(result.baseline.a, { days: [3], time: '15:00' });
});

test('pagination does not grow a cache of every previously visited clean row', () => {
  let drafts: Record<string, { title: string }> = {};
  let baseline: Record<string, { title: string }> = {};
  for (let page = 0; page < 100; page++) {
    const incoming = Object.fromEntries(
      Array.from({ length: 20 }, (_, n) => [`${page}-${n}`, { title: 'Row' }]),
    );
    ({ drafts, baseline } = reconcileContentDrafts(drafts, baseline, incoming));
    assert.equal(Object.keys(drafts).length, 20);
    assert.equal(Object.keys(baseline).length, 20);
  }
});

test('successful saves refresh canonical values without losing later unsaved input', () => {
  const current = { a: { name: 'Saved ' }, b: { name: 'Typed after request' } };
  const baseline = { a: { name: 'Before' }, b: { name: 'Before' } };
  acceptContentDraft(current, baseline, 'a', { name: 'Saved ' }, { name: 'Saved' });
  acceptContentDraft(current, baseline, 'b', { name: 'Submitted' }, { name: 'Submitted' });
  assert.deepEqual(current, { a: { name: 'Saved' }, b: { name: 'Typed after request' } });
  assert.deepEqual(baseline, { a: { name: 'Saved' }, b: { name: 'Submitted' } });
});

test('a saved row that leaves the filter does not remain a clean off-page cache', () => {
  const current: Record<string, { name: string }> = { a: { name: 'Submitted' } };
  const baseline = { a: { name: 'Before' } };
  acceptContentDraft(current, baseline, 'a', { name: 'Submitted' }, undefined);
  assert.deepEqual(current, {});
  assert.deepEqual(baseline, {});
});

test('embedded option search cannot submit the surrounding content or profile form', () => {
  const source = readFileSync(
    new URL('../../../../resources/js/components/content/ContentChoicePicker.vue', import.meta.url),
    'utf8',
  );
  assert.doesNotMatch(source, /<form\b/i);
  assert.match(source, /@keydown\.enter\.prevent\.stop="load\(\)"/);
  assert.ok(
    [...source.matchAll(/<AppButton\b([\s\S]*?)>/g)].every((match) =>
      /type="button"/.test(match[1]!),
    ),
  );
});

test('each content locale describes page scope, failed loads and selected-versus-total retry counts', async () => {
  const root = new URL('../../../../resources/js/localization/messages/content/', import.meta.url);
  const files = readdirSync(root).filter((name) => name.endsWith('.ts'));
  assert.equal(files.length, 17);
  for (const name of files) {
    const { default: messages } = await import(new URL(name, root).href);
    const keys = messages.contentExperience;
    for (const key of [
      'pageRecords',
      'collectionFailed',
      'selectedUnavailable',
      'reviewOnThisPage',
      'allStatuses',
      'retryCandidateSummary',
    ]) {
      assert.equal(typeof keys[key], 'string', name + ':' + key);
      assert.ok(keys[key].length > 0, name + ':' + key);
    }
    for (const placeholder of ['{count}', '{total}'])
      assert.ok(keys.pageRecords.includes(placeholder), name);
    for (const placeholder of ['{selected}', '{total}'])
      assert.ok(keys.retryCandidateSummary.includes(placeholder), name);
  }
});
