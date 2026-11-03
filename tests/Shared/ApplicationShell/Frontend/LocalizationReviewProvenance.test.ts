import assert from 'node:assert/strict';
import test from 'node:test';
import { binding, check, hash, validate } from '../../../../scripts/check-localization-review-provenance.mjs';

const make = () => {
  const r = {
    version: 1, decision: 'approved', scope: 'native-translation',
    catalogue: 'client', domain: 'account', key: 'reset.title', locale: 'fr',
    reviewer: 'qualified-human', reviewedPr: 193, reviewId: 105,
    reviewedCommitSha: 'a'.repeat(40),
    sourceSha256: hash('Reset account'), translationSha256: hash('Réinitialiser le compte'),
  };
  return { ...r, reviewBindingSha256: binding(r) };
};
const exported = {
  client: { account: { en: { 'reset.title': 'Reset account' }, fr: { 'reset.title': 'Réinitialiser le compte' } } },
  server: {}, php: {},
};

test('a real externally verified approval is bound to its exact reviewed text', async () => {
  assert.equal(await check(make(), exported, async () => true), 'client:account:reset.title:fr');
});
test('a self-issued approved JSON is not a GitHub approving review', async () => {
  await assert.rejects(check(make(), exported, async () => false), /missing matching GitHub/);
});
test('a modified reviewer breaks the bound digest', () => {
  assert.throws(() => validate({ ...make(), reviewer: 'someone-else' }), /unsigned or altered/);
});
test('a changed translation makes an earlier record stale', async () => {
  const changed = {
    ...exported,
    client: { account: { en: { 'reset.title': 'Reset account' }, fr: { 'reset.title': 'Changer le compte' } } },
  };
  await assert.rejects(check(make(), changed, async () => true), /stale translation/);
});
test('invalid locale, fake review locator and missing translation fail closed', async () => {
  assert.throws(() => validate({ ...make(), locale: 'zz' }), /unknown locale/);
  assert.throws(() => validate({ ...make(), reviewId: 0 }), /invalid review/);
  await assert.rejects(check(make(), { client: {}, server: {}, php: {} }, async () => true), /unresolvable/);
});
