import assert from 'node:assert/strict';
import test from 'node:test';
import {
  catalogueProblems,
  preservedTranslation,
} from '../../../../scripts/lib/localization-completeness.mjs';

test('missing translations cannot pass by falling back to English', () => {
  assert.equal(catalogueProblems({ label: 'Save' }, {}).at(0).reason, 'missing');
  assert.equal(
    catalogueProblems({ label: 'Save' }, { label: 'Save' }).at(0).reason,
    'untranslated',
  );
  assert.deepEqual(catalogueProblems({ label: 'Save' }, { label: 'Enregistrer' }), []);
});

test('parameters preserve multiplicity and case, including queued mail parameters', () => {
  const source = { label: '{count} of {count}; :minutes' };
  assert.equal(
    catalogueProblems(source, { label: '{count} ; :minutes' }).at(0).reason,
    'parameters',
  );
  assert.equal(
    catalogueProblems(source, { label: '{Count} sur {count} ; :minutes' }).at(0).reason,
    'parameters',
  );
  assert.deepEqual(catalogueProblems(source, { label: ':minutes ; {count} sur {count}' }), []);
});

test('unchanged names require an explicit exception', () => {
  const source = { brand: 'Kingshot', action: 'Save' };
  assert.deepEqual(
    catalogueProblems(source, source, ['Kingshot']).map((item: { key: string }) => item.key),
    ['action'],
  );
});

test('English overlays cannot erase an existing translation of unchanged source text', () => {
  assert.equal(preservedTranslation('Save', 'Save', 'Save', 'Enregistrer'), 'Enregistrer');
  assert.equal(preservedTranslation('Save', 'Sauvegarder', 'Save', 'Enregistrer'), 'Sauvegarder');
  assert.equal(preservedTranslation('Save changes', 'Save changes', 'Save', 'Enregistrer'), null);
});
