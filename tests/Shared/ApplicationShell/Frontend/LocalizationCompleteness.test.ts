import assert from 'node:assert/strict';
import test from 'node:test';
import {
  catalogueProblems,
  hasInventedNumber,
  preservedTranslation,
} from '../../../../scripts/lib/localization-completeness.mjs';

test('translation cannot invent dates, quantities or unrelated version numbers', () => {
  assert.equal(hasInventedNumber('Published at', 'Veröffentlicht am 1. Mai 2017'), true);
  assert.equal(
    hasInventedNumber('This browser does not report passkey support.', '不支持HTML5视频'),
    true,
  );
  assert.equal(hasInventedNumber('Phase II', '第2阶段'), false);
  assert.equal(hasInventedNumber('{count} messages', '{count} messages'), false);
  assert.equal(hasInventedNumber('One more attempt', '1 weiterer Versuch'), false);
});

test('missing translations cannot pass by falling back to English', () => {
  assert.equal(catalogueProblems({ label: 'Save' }, {}).at(0).reason, 'missing');
  assert.equal(
    catalogueProblems({ label: 'Save' }, { label: 'Save' }).at(0).reason,
    'untranslated',
  );
  assert.deepEqual(catalogueProblems({ label: 'Save' }, { label: 'Enregistrer' }), []);
});

test('cosmetic formatting cannot disguise English fallback', () => {
  const english = { label: 'SECURE PASSWORD RESET' };
  for (const label of [
    'SECURE Password RESET',
    ' secure   password reset ',
    'SECURE\u00a0PASSWORD RESET',
    'ＳＥＣＵＲＥ ＰＡＳＳＷＯＲＤ ＲＥＳＥＴ',
    'Secure—password—reset',
    '♪ Secure password reset ♪',
    'Sec\u200bure password reset.',
  ]) {
    assert.equal(catalogueProblems(english, { label }).at(0).reason, 'untranslated');
  }
  assert.deepEqual(catalogueProblems(english, { label: 'Redefinição segura de senha' }), []);
  assert.deepEqual(
    catalogueProblems({ label: 'Alliance' }, { label: 'alliance' }, ['Alliance']),
    [],
  );
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
