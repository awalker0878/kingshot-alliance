import assert from 'node:assert/strict';
import test from 'node:test';
import { binding, hash } from '../../../../scripts/check-localization-review-provenance.mjs';
import {
  checkReleaseCoverage,
  scopeBinding,
} from '../../../../scripts/check-localization-release-coverage.mjs';

function fixture() {
  const unit = {
    catalogue: 'client',
    domain: 'account',
    key: 'reset.title',
    locale: 'fr',
    sourceSha256: hash('Reset account'),
    translationSha256: hash('Réinitialiser le compte'),
  };
  const record = {
    ...unit,
    version: 2,
    decision: 'approved',
    scope: 'native-translation',
    reviewer: 'native-reviewer',
    reviewedPr: 100,
    reviewId: 10,
    reviewedCommitSha: 'a'.repeat(40),
    reviewBindingSha256: '',
  };
  record.reviewBindingSha256 = binding(record);
  const scope = {
    version: 1,
    status: 'approved',
    description: 'Independently selected account recovery scope',
    sourceRevision: 'b'.repeat(40),
    classifierSha256: hash('classifier'),
    localeRegistrySha256: hash('registry'),
    units: [unit],
    approval: {
      reviewer: 'source-reviewer',
      reviewedPr: 101,
      reviewId: 11,
      reviewedCommitSha: 'b'.repeat(40),
      scopeBindingSha256: '',
    },
  };
  scope.approval.scopeBindingSha256 = scopeBinding(scope);
  return {
    scope,
    records: [record],
    data: {
      client: {
        account: {
          en: { 'reset.title': 'Reset account' },
          fr: { 'reset.title': 'Réinitialiser le compte' },
        },
      },
      server: {},
      php: {},
    },
    assignments: {
      version: 1,
      assignments: {
        fr: [
          {
            login: 'native-reviewer',
            qualified: true,
            status: 'active',
            catalogues: ['client'],
            domains: ['account'],
          },
        ],
      },
    },
    scopeApprovers: {
      version: 1,
      approvers: [{ login: 'source-reviewer', status: 'active', qualifiedForSourceRisk: true }],
    },
    classifierSha256: hash('classifier'),
    localeRegistrySha256: hash('registry'),
    scopeVerifier: async () => true,
    nativeVerifier: async () => true,
  };
}

test('only all verified units in an independently approved nonempty scope pass', async () => {
  const result = await checkReleaseCoverage(fixture());
  assert.equal(result.status, 'APPROVED_SCOPE_COVERED');
  assert.equal(result.verifiedUnits, 1);
  assert.equal(result.nativeTranslationReleaseApproved, true);
});

test('empty bootstrap, empty approved scope and missing native reviews all fail release', async () => {
  const bootstrap = fixture();
  bootstrap.scope.status = 'review_required';
  bootstrap.records = [];
  await assert.rejects(
    checkReleaseCoverage(bootstrap),
    /scope has not been independently approved/,
  );
  const empty = fixture();
  empty.scope.units = [];
  await assert.rejects(checkReleaseCoverage(empty), /empty scope cannot approve/);
  const missing = fixture();
  missing.records = [];
  await assert.rejects(checkReleaseCoverage(missing), /1 of 1.*lack verified native approvals/);
});

test('scope tampering, stale classifier or registry and unassigned owners fail', async () => {
  const tampered = fixture();
  tampered.scope.units[0].key = 'another.key';
  await assert.rejects(checkReleaseCoverage(tampered), /Altered or unsigned/);
  const classifier = fixture();
  classifier.classifierSha256 = hash('changed rules');
  await assert.rejects(checkReleaseCoverage(classifier), /Stale classifier/);
  const registry = fixture();
  registry.localeRegistrySha256 = hash('new locale');
  await assert.rejects(checkReleaseCoverage(registry), /Stale locale registry/);
  const owner = fixture();
  owner.scopeApprovers.approvers = [];
  await assert.rejects(checkReleaseCoverage(owner), /Unassigned scope approver/);
});

test('forged or dismissed scope/native approval cannot be replaced with a count', async () => {
  const badScope = fixture();
  badScope.scopeVerifier = async () => false;
  await assert.rejects(checkReleaseCoverage(badScope), /GitHub scope approval/);
  const badNative = fixture();
  badNative.nativeVerifier = async () => false;
  await assert.rejects(checkReleaseCoverage(badNative), /GitHub APPROVED review/);
  const unassigned = fixture();
  unassigned.assignments.assignments.fr = [];
  await assert.rejects(checkReleaseCoverage(unassigned), /not a qualified/);
});

test('changed content, duplicate units, duplicate records and unknown locales fail closed', async () => {
  const stale = fixture();
  stale.data.client.account.fr['reset.title'] = 'Changer le compte';
  await assert.rejects(checkReleaseCoverage(stale), /Stale scope translation/);
  const units = fixture();
  units.scope.units.push(units.scope.units[0]);
  units.scope.approval.scopeBindingSha256 = scopeBinding(units.scope);
  await assert.rejects(checkReleaseCoverage(units), /Duplicate release review unit/);
  const records = fixture();
  records.records.push(records.records[0]);
  await assert.rejects(checkReleaseCoverage(records), /Duplicate native approval/);
  const unknown = fixture();
  unknown.scope.units[0].locale = 'zz';
  unknown.scope.approval.scopeBindingSha256 = scopeBinding(unknown.scope);
  await assert.rejects(checkReleaseCoverage(unknown), /Unknown release locale/);
});
