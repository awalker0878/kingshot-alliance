import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import {
  authenticatedApprovalMatches,
  check,
  githubReview,
  githubReviewEvidence,
  hash,
  id,
  main as checkIntegrity,
  values,
} from './check-localization-review-provenance.mjs';
import { translatedLocaleCodes } from './localization/locale-registry.mjs';

const hex = /^[a-f0-9]{64}$/;
const sha = /^[a-f0-9]{40}$/;
const locales = new Set(translatedLocaleCodes());
export const unitId = (unit) =>
  JSON.stringify([unit.catalogue, unit.domain, unit.key, unit.locale]);
export const scopeBinding = (scope) =>
  hash(
    JSON.stringify([
      'kingshot-localization-release-scope-v1',
      scope.version,
      scope.status,
      scope.description,
      scope.sourceRevision,
      scope.classifierSha256,
      scope.localeRegistrySha256,
      scope.units
        .map((unit) => [
          unit.catalogue,
          unit.domain,
          unit.key,
          unit.locale,
          unit.sourceSha256,
          unit.translationSha256,
        ])
        .sort((a, b) =>
          JSON.stringify(a) < JSON.stringify(b)
            ? -1
            : JSON.stringify(a) > JSON.stringify(b)
              ? 1
              : 0,
        ),
    ]),
  );

export async function verifyScopeApproval(scope) {
  const { review, pr } = await githubReviewEvidence(scope.approval);
  return authenticatedApprovalMatches(
    scope.approval,
    review,
    pr,
    'Localization-Scope-SHA256: ' + scopeBinding(scope),
  );
}

export async function checkReleaseCoverage({
  scope,
  records,
  data,
  assignments,
  scopeApprovers,
  classifierSha256,
  localeRegistrySha256,
  scopeVerifier = verifyScopeApproval,
  nativeVerifier = githubReview,
}) {
  assert.equal(scope?.version, 1, 'Unsupported release scope');
  assert.equal(
    scope.status,
    'approved',
    'REVIEW_REQUIRED: release scope has not been independently approved',
  );
  assert.ok(
    typeof scope.description === 'string' && scope.description.trim(),
    'Describe the approved scope',
  );
  assert.ok(sha.test(scope.sourceRevision), 'Missing immutable scope revision');
  assert.ok(
    hex.test(scope.classifierSha256) && scope.classifierSha256 === classifierSha256,
    'Stale classifier binding',
  );
  assert.ok(
    hex.test(scope.localeRegistrySha256) && scope.localeRegistrySha256 === localeRegistrySha256,
    'Stale locale registry binding',
  );
  assert.ok(
    Array.isArray(scope.units) && scope.units.length > 0,
    'An empty scope cannot approve a release',
  );
  assert.ok(
    scope.approval && typeof scope.approval.reviewer === 'string',
    'Missing independent scope approval',
  );
  const approval = scope.approval;
  assert.ok(/^[a-z0-9-]{1,39}$/i.test(approval.reviewer), 'Invalid scope approver identity');
  assert.ok(
    Number.isSafeInteger(approval.reviewedPr) &&
      approval.reviewedPr > 0 &&
      Number.isSafeInteger(approval.reviewId) &&
      approval.reviewId > 0,
    'Invalid scope approval reference',
  );
  assert.equal(
    approval.reviewedCommitSha,
    scope.sourceRevision,
    'Scope approval must attest the frozen source revision',
  );
  assert.equal(
    approval.scopeBindingSha256,
    scopeBinding(scope),
    'Altered or unsigned release scope',
  );
  assert.ok(
    scopeApprovers?.version === 1 && Array.isArray(scopeApprovers.approvers),
    'Missing scope approver assignments',
  );
  assert.ok(
    scopeApprovers.approvers.some(
      (person) =>
        typeof person.login === 'string' &&
        person.login.toLowerCase() === approval.reviewer.toLowerCase() &&
        person.status === 'active' &&
        person.qualifiedForSourceRisk === true,
    ),
    'Unassigned scope approver',
  );
  assert.equal(
    await scopeVerifier(scope),
    true,
    'Missing matching current-head GitHub scope approval',
  );

  const byId = new Map();
  for (const record of records) {
    assert.ok(!byId.has(unitId(record)), 'Duplicate native approval record');
    byId.set(unitId(record), record);
  }
  const seen = new Set(),
    missing = [];
  for (const unit of scope.units) {
    assert.ok(
      ['client', 'server'].includes(unit.catalogue) &&
        typeof unit.domain === 'string' &&
        unit.domain &&
        typeof unit.key === 'string' &&
        unit.key,
      'Invalid scope message identity',
    );
    assert.ok(locales.has(unit.locale), 'Unknown release locale');
    assert.ok(!seen.has(unitId(unit)), 'Duplicate release review unit');
    seen.add(unitId(unit));
    const current = values(unit, data);
    assert.ok(
      hex.test(unit.sourceSha256) && hash(current.source) === unit.sourceSha256,
      'Stale scope source: ' + id(unit),
    );
    assert.ok(
      hex.test(unit.translationSha256) && hash(current.translation) === unit.translationSha256,
      'Stale scope translation: ' + id(unit),
    );
    const record = byId.get(unitId(unit));
    if (!record) {
      missing.push(id(unit));
      continue;
    }
    // Coverage never trusts a record's "approved" field or an integrity count.
    await check(record, data, assignments, nativeVerifier);
  }
  const report = {
    status: missing.length ? 'REVIEW_REQUIRED' : 'APPROVED_SCOPE_COVERED',
    requiredUnits: seen.size,
    verifiedUnits: seen.size - missing.length,
    missing,
    scopeSha256: scopeBinding(scope),
    nativeTranslationReleaseApproved: missing.length === 0,
  };
  if (missing.length)
    throw Object.assign(
      new Error(
        `REVIEW_REQUIRED: ${missing.length} of ${seen.size} approved-scope units lack verified native approvals`,
      ),
      { report },
    );
  return report;
}

export async function main(root = process.cwd()) {
  const read = (file) => JSON.parse(readFileSync(path.join(root, file), 'utf8'));
  let report;
  try {
    const { records, data, assignments } = await checkIntegrity(root);
    report = await checkReleaseCoverage({
      scope: read('resources/data/localization/release-review-scope.json'),
      scopeApprovers: read('resources/data/localization/scope-approvers.json'),
      records,
      data,
      assignments,
      classifierSha256: hash(
        readFileSync(path.join(root, 'scripts/report-localization-tier1a-v2.mjs')),
      ),
      localeRegistrySha256: hash(
        readFileSync(path.join(root, 'resources/data/localization/locales.json')),
      ),
    });
    console.log('NATIVE RELEASE COVERAGE: ' + JSON.stringify(report));
    return report;
  } catch (error) {
    report = error.report ?? {
      status: 'REVIEW_REQUIRED',
      nativeTranslationReleaseApproved: false,
      reason: error.message,
    };
    throw error;
  } finally {
    if (report)
      writeFileSync(
        path.join(root, '.translation-pr6-current/release-coverage.json'),
        JSON.stringify(report, null, 2) + '\n',
      );
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  main().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
  });
}
