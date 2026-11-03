import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import {
  pageTranslationContracts,
  placeholders,
  translationContract,
} from '../../../../scripts/lib/localization-contracts.mjs';

test('translation scanner reads script, template, conditional keys and parameter names', () => {
  const contract = translationContract(
    `<script setup lang="ts">
    // t('ignored.comment')
    const example = "t('ignored.string')";
    const title = t('account.title');
    const label = t(dynamicKey, params);
  </script><template>
    <button :aria-label="t('common.open', { name: title })">
      {{ t(active ? 'common.yes' : 'common.no') }}
    </button>
  </template>`,
    'fixture.vue',
  );
  assert.deepEqual(contract.calls.map((call) => call.key).sort(), [
    'account.title',
    'common.no',
    'common.open',
    'common.yes',
  ]);
  assert.deepEqual(contract.calls.find((call) => call.key === 'common.open')?.parameterNames, [
    'name',
  ]);
  assert.equal(contract.dynamicCalls, 1);
  assert.deepEqual(placeholders('{count} items for {name}; {count}'), ['count', 'name']);
});

test('translation scanner follows shared components and cyclic composables once', () => {
  const root = mkdtempSync(path.join(tmpdir(), 'localization-contract-'));
  try {
    const directory = path.join(root, 'resources/js');
    mkdirSync(directory, { recursive: true });
    writeFileSync(
      path.join(directory, 'Page.vue'),
      `<script setup>const Child = defineAsyncComponent(() => import('@/Child.vue'));</script><template><Child /></template>`,
    );
    writeFileSync(
      path.join(directory, 'Child.vue'),
      `<script setup>import { label } from './labels';</script><template>{{ t('child.title') }}</template>`,
    );
    writeFileSync(
      path.join(directory, 'labels.ts'),
      `import Child from './Child.vue'; const label = t('child.help');`,
    );
    const result = pageTranslationContracts(path.join(directory, 'Page.vue'), root);
    assert.deepEqual(result.calls.map((call) => call.key).sort(), ['child.help', 'child.title']);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
