import assert from 'node:assert/strict';
import test from 'node:test';
import {
  assignedReviewer, binding, bootstrapStatus, check, hash,
  reviewEvidenceMatches, validate,
} from '../../../../scripts/check-localization-review-provenance.mjs';

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
const assignees = { version: 1, assignments: { fr: [{
  login: 'qualified-human', qualified: true, status: 'active',
  catalogues: ['client'], domains: ['account'],
}] } };
const snapshots = () => ({
  review: { state: 'APPROVED', user: { login: 'qualified-human' }, commit_id: 'a'.repeat(40),
    submitted_at: '2026-11-02T18:00:00Z', body: 'Approval-Record-SHA256: ' + make().reviewBindingSha256 },
  pr: { number: 193, user: { login: 'pr-author' }, head: { sha: 'a'.repeat(40) } },
});

test('correct assigned reviewer, unchanged strings, and external positive verification', async () => {
  assert.equal(await check(make(), exported, assignees, async () => true), 'client:account:reset.title:fr');
  const { review, pr } = snapshots();
  assert.equal(reviewEvidenceMatches(make(), review, pr), true);
});
test('wrong account and no-assignee bootstrap cannot claim approval', async () => {
  const none = { version: 1, assignments: {} };
  assert.throws(() => assignedReviewer(make(), none), /not a qualified, active assignee/);
  await assert.rejects(check(make(), exported, none, async () => true), /not a qualified, active assignee/);
  assert.throws(() => assignedReviewer({ ...make(), reviewer: 'other-reviewer' }, assignees), /not a qualified/);
  const status = bootstrapStatus(511, 0);
  assert.equal(status.status, 'REVIEW_REQUIRED');
  assert.equal(status.reviewRequired, 8176);
  assert.equal(status.nativeTranslationReleaseApproved, false);
});
test('stale GitHub review head, wrong account, dismissed review, and self-approval fail closed', () => {
  const r = make();
  const { review, pr } = snapshots();
  assert.equal(reviewEvidenceMatches(r, review, { ...pr, head: { sha: 'b'.repeat(40) } }), false);
  assert.equal(reviewEvidenceMatches(r, { ...review, user: { login: 'impersonator' } }, pr), false);
  assert.equal(reviewEvidenceMatches(r, { ...review, state: 'DISMISSED' }, pr), false);
  assert.equal(reviewEvidenceMatches(r, review, { ...pr, user: { login: r.reviewer } }), false);
  assert.equal(reviewEvidenceMatches(r, review, { ...pr, user: undefined }), false);
  assert.equal(reviewEvidenceMatches(r, { ...review, body: 'Approved but without digest' }, pr), false);
});
test('self-issued record, invalid identity, tampered digest, or stale text fails', async () => {
  await assert.rejects(check(make(), exported, assignees, async () => false), /missing matching CURRENT HEAD/);
  assert.throws(() => validate({ ...make(), reviewer: 'another-user' }), /unsigned or altered/);
  assert.throws(() => validate({ ...make(), locale: 'zz' }), /unknown locale/);
  assert.throws(() => validate({ ...make(), reviewId: 0 }), /invalid review/);
  const changed = { ...exported, client: { account: {
    en: { 'reset.title': 'Reset account' }, fr: { 'reset.title': 'Changer le compte' },
  } } };
  await assert.rejects(check(make(), changed, assignees, async () => true), /stale translation/);
});
test('missing translation or wrong domain assignment fails closed', async () => {
  await assert.rejects(check(make(), { client: {}, server: {}, php: {} }, assignees, async () => true), /unresolvable/);
  assert.throws(() => assignedReviewer(make(), { version: 1, assignments: { fr: [{
    login: 'qualified-human', qualified: true, status: 'active',
    catalogues: ['client'], domains: ['governance'],
  }] } }), /not a qualified/);
});
