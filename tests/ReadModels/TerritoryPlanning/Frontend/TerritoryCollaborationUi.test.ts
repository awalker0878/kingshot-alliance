import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
  editableAllianceKeys,
  privateShareFragment,
  reviewableAllianceKeys,
  uniquePlayerOptions,
  type CollaborationOverview,
} from '../../../../resources/js/features/territory-planner/engine/collaboration.ts';

function overview(overrides: Partial<CollaborationOverview> = {}): CollaborationOverview {
  return {
    current_revision: 4,
    current_snapshot_checksum: 'a'.repeat(64),
    can_manage: false,
    can_review: false,
    editable_alliance_keys: ['alpha'],
    grants: [],
    ...overrides,
  };
}

test('delegated editing is limited to explicit alliance layers', () => {
  assert.deepEqual([...editableAllianceKeys(overview())], ['alpha']);
});

test('reviewable layers ignore revoked and expired grants', () => {
  const now = Date.parse('2026-09-14T00:00:00Z');
  const value = reviewableAllianceKeys(
    overview({
      grants: [
        { id: '1', player_id: 'p', alliance_key: 'alpha', permission: 'review', expires_at: '2026-09-15T00:00:00Z', revoked_at: null },
        { id: '2', player_id: 'p', alliance_key: 'beta', permission: 'edit', expires_at: '2026-09-13T00:00:00Z', revoked_at: null },
        { id: '3', player_id: 'p', alliance_key: 'gamma', permission: 'review', expires_at: '2026-09-15T00:00:00Z', revoked_at: '2026-09-13T23:00:00Z' },
      ],
    }),
    ['alpha', 'beta', 'gamma'],
    now,
  );
  assert.deepEqual([...value], ['alpha']);
});

test('manager review scope covers every plan alliance', () => {
  assert.deepEqual([...reviewableAllianceKeys(overview({ can_manage: true }), ['a', 'b'])], ['a', 'b']);
});

test('collaboration player choices deduplicate roster identities', () => {
  assert.deepEqual(
    uniquePlayerOptions({ alpha: [{ id: '2', name: 'Zoe' }, { id: '1', name: 'Adam' }], beta: [{ id: '1', name: 'Adam' }] }),
    [{ id: '1', name: 'Adam' }, { id: '2', name: 'Zoe' }],
  );
});

test('private share token is placed in the URL fragment, never its query string', () => {
  const path = privateShareFragment('share/id', 'secret+token');
  assert.equal(path, '/territory/shared/share%2Fid#token=secret%2Btoken');
  assert.equal(path.includes('?'), false);
});


test('private share viewer clears the fragment before resolving through no-referrer POST', () => {
  const source = readFileSync('resources/js/pages/Kingdom/Territory/Shared.vue', 'utf8');
  assert.match(source, /history\.replaceState\(null, '', window\.location\.pathname\)/);
  assert.match(source, /method: 'POST'/);
  assert.match(source, /cache: 'no-store'/);
  assert.match(source, /referrerPolicy: 'no-referrer'/);
  assert.match(source, /JSON\.stringify\(\{ token \}\)/);
  assert.doesNotMatch(source, /location\.search/);
  assert.match(source, /<TerritoryCanvas/);
});
