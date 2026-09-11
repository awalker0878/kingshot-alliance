import assert from 'node:assert/strict';
import test from 'node:test';
import { effectScope, nextTick, ref } from 'vue';
import { useTransferDrafts } from '../../../../../resources/js/components/transfers/useTransferDrafts.ts';

test('page changes initialize new rows, retain edits, and discard unseen clean rows', async () => {
  const scope = effectScope();
  const rows = ref([
    { id: 'first', name: 'Original' },
    { id: 'clean', name: 'Clean' },
  ]);
  const drafts = scope.run(() =>
    useTransferDrafts(
      () => rows.value,
      () => 'alliance:plan',
      (row) => ({ name: row.name }),
    ),
  )!;
  try {
    assert.deepEqual(Object.keys(drafts), ['first', 'clean']);
    drafts.first.name = 'Unsaved officer edit';
    rows.value = [{ id: 'next', name: 'Next' }];
    await nextTick();
    assert.deepEqual(Object.keys(drafts).sort(), ['first', 'next']);
    assert.equal(drafts.first.name, 'Unsaved officer edit');
    rows.value = [{ id: 'first', name: 'Original' }];
    await nextTick();
    assert.equal(drafts.first.name, 'Unsaved officer edit');
    assert.equal('next' in drafts, false);
  } finally {
    scope.stop();
  }
});

test('visiting many clean pages does not accumulate a copy of the participant set', async () => {
  const scope = effectScope();
  const rows = ref([{ id: '0', name: 'Name' }]);
  const drafts = scope.run(() =>
    useTransferDrafts(
      () => rows.value,
      () => 'alliance:plan',
      (row) => ({ name: row.name }),
    ),
  )!;
  try {
    for (let page = 1; page <= 100; page++) {
      rows.value = Array.from({ length: 25 }, (_, offset) => ({
        id: `${page}:${offset}`,
        name: 'Name',
      }));
      await nextTick();
      assert.equal(Object.keys(drafts).length, 25);
    }
  } finally {
    scope.stop();
  }
});

test('principal scope changes discard hidden and visible edits even inside the same Alliance and Plan', async () => {
  const scope = effectScope();
  const owner = ref('actorA:allianceA:planA');
  const rows = ref([{ id: 'old', name: 'Original' }]);
  const drafts = scope.run(() =>
    useTransferDrafts(
      () => rows.value,
      () => owner.value,
      (row) => ({ name: row.name }),
    ),
  )!;
  try {
    drafts.old.name = 'Private draft';
    rows.value = [{ id: 'next', name: 'Next' }];
    await nextTick();
    owner.value = 'actorB:allianceA:planA';
    rows.value = [{ id: 'new', name: 'New owner' }];
    await nextTick();
    assert.deepEqual(Object.keys(drafts), ['new']);
    assert.equal(drafts.new.name, 'New owner');
  } finally {
    scope.stop();
  }
});

test('server refreshes update clean drafts, preserve unsaved edits, and release saved edits', async () => {
  const scope = effectScope();
  const rows = ref([{ id: 'first', readiness: 'preparing' }]);
  const drafts = scope.run(() =>
    useTransferDrafts(
      () => rows.value,
      () => 'same-plan',
      (row) => row.readiness,
    ),
  )!;
  try {
    rows.value = [{ id: 'first', readiness: 'ready' }];
    await nextTick();
    assert.equal(drafts.first, 'ready');
    drafts.first = 'confirmed';
    rows.value = [{ id: 'first', readiness: 'blocked' }];
    await nextTick();
    assert.equal(drafts.first, 'confirmed');
    // The real response after a successful save becomes the clean baseline.
    rows.value = [{ id: 'first', readiness: 'confirmed' }];
    await nextTick();
    rows.value = [];
    await nextTick();
    assert.equal(Object.keys(drafts).length, 0);
  } finally {
    scope.stop();
  }
});
