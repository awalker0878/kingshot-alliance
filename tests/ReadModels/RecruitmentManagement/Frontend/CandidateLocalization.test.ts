import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import recruitment from '../../../../resources/js/localization/messages/recruitment/en.ts';
import core from '../../../../resources/js/localization/messages/core/en.ts';

test('candidate static translation references resolve to existing catalogue text', () => {
  const source = readFileSync(
    new URL('../../../../resources/js/pages/Alliance/Recruitment/Candidate.vue', import.meta.url),
    'utf8',
  );
  const catalogue: Record<string, unknown> = { ...core, ...recruitment };
  const keys = [...source.matchAll(/\bt\('((?:recruitment|common)\.[^']+)'/g)].map(
    (match) => match[1],
  );
  assert.ok(
    keys.length > 20,
    'The real candidate page must contribute its translation references.',
  );
  for (const key of keys) {
    let value: unknown = catalogue;
    for (const part of key.split('.'))
      value =
        typeof value === 'object' && value !== null
          ? (value as Record<string, unknown>)[part]
          : undefined;
    assert.equal(typeof value, 'string', `Missing candidate label: ${key}`);
  }
});
