import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { buildLayoutDocument } from '../../../../../resources/js/features/territory-planner/engine/interchange.ts';

test('browser export matches the same canonical V2 fixture decoded by the PHP owner', () => {
  const fixture = JSON.parse(readFileSync(new URL('../Fixtures/browser-layout-v2.json', import.meta.url), 'utf8'));
  const plan = { ...fixture.plan, revision: 7, status: 'draft', can_manage: true, secret_ui_property: 'excluded' };
  const exported = buildLayoutDocument(plan, 7, plan.map_dataset_id, plan.map_dataset_checksum, {
    alliances: fixture.alliances, groups: fixture.groups, objects: fixture.objects, preferences: plan.planning_preferences,
  });
  assert.deepEqual(exported, fixture);
  assert.equal('revision' in exported.plan, false);
  assert.equal('can_manage' in exported.plan, false);
  assert.throws(() => buildLayoutDocument(plan, 0, plan.map_dataset_id, plan.map_dataset_checksum,
    { ...fixture, preferences: {} }), /revision/);
});
