import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { strictReasons } from './report-localization-tier1a.mjs';

export const hash = (v) => createHash('sha256').update(v, 'utf8').digest('hex');
const hex = /^[0-9a-f]{64}$/;
const commit = /^[0-9a-f]{40}$/;
const localeCodes = new Set(['ar','de','es','fr','id','it','ja','ko','pl','pt-BR','ru','th','tr','vi','zh-CN','zh-TW']);
export const id = (r) => [r.catalogue,r.domain,r.key,r.locale].join(':');
export const binding = (r) => hash(JSON.stringify([
  'kingshot-native-review-v1',r.catalogue,r.domain,r.key,r.locale,
  r.sourceSha256,r.translationSha256,r.reviewer.toLowerCase(),
  r.reviewedPr,r.reviewedCommitSha,
]));
export function validate(r) {
  if (!r || typeof r !== 'object' || Array.isArray(r)) throw Error('invalid record');
  if (r.version !== 1 || r.decision !== 'approved' || r.scope !== 'native-translation') throw Error('unapproved record');
  if (!['client','server'].includes(r.catalogue) || typeof r.domain !== 'string' || !r.domain || typeof r.key !== 'string' || !r.key) throw Error('invalid message identity');
  if (!localeCodes.has(r.locale)) throw Error('unknown locale');
  if (typeof r.reviewer !== 'string' || !/^[a-z0-9-]{1,39}$/i.test(r.reviewer)) throw Error('unknown reviewer identity');
  if (!Number.isSafeInteger(r.reviewedPr) || r.reviewedPr < 1 || !Number.isSafeInteger(r.reviewId) || r.reviewId < 1) throw Error('invalid review reference');
  if (!commit.test(r.reviewedCommitSha)) throw Error('invalid review head SHA');
  for (const k of ['sourceSha256','translationSha256','reviewBindingSha256']) if (!hex.test(r[k])) throw Error('invalid digest: ' + k);
  if (r.reviewBindingSha256 !== binding(r)) throw Error('unsigned or altered review record');
}
export function values(r, exported) {
  let source,translation;
  if (r.catalogue === 'client') {
    source = exported.client[r.domain]?.en?.[r.key];
    translation = exported.client[r.domain]?.[r.locale]?.[r.key];
  } else if (r.domain === 'json') {
    source = exported.server.en?.[r.key];
    translation = exported.server[r.locale]?.[r.key];
  } else if (r.domain === 'accounts' && /^accounts\.php#[1-9]\d*$/.test(r.key)) {
    const i = Number(r.key.slice(13)) - 1;
    source = exported.php.en?.['accounts.php']?.[i]?.value;
    translation = exported.php[r.locale]?.['accounts.php']?.[i]?.value;
  }
  if (typeof source !== 'string' || typeof translation !== 'string') throw Error('unresolvable source/translation: ' + id(r));
  return {source,translation};
}
export function assignedReviewer(r, manifest) {
  if (manifest?.version !== 1 || !manifest.assignments || typeof manifest.assignments !== 'object'
    || Array.isArray(manifest.assignments)) throw Error('invalid reviewer assignment manifest');
  const entries = manifest.assignments[r.locale] ?? [];
  if (!Array.isArray(entries)) throw Error('invalid locale assignment list: ' + r.locale);
  const matched = entries.some((person) => person && typeof person.login === 'string'
    && person.login.toLowerCase() === r.reviewer.toLowerCase()
    && person.qualified === true && person.status === 'active'
    && Array.isArray(person.catalogues) && person.catalogues.includes(r.catalogue)
    && Array.isArray(person.domains) && (person.domains.includes('*') || person.domains.includes(r.domain)));
  if (!matched) throw Error('reviewer is not a qualified, active assignee for locale/domain: ' + id(r));
  return true;
}

export function reviewEvidenceMatches(r, review, pr) {
  return review?.state === 'APPROVED'
    && review.user?.login?.toLowerCase() === r.reviewer.toLowerCase()
    && review.commit_id === r.reviewedCommitSha
    && pr?.head?.sha === r.reviewedCommitSha
    && typeof pr.user?.login === 'string'
    && pr.user.login.toLowerCase() !== r.reviewer.toLowerCase()
    && pr?.number === r.reviewedPr
    && typeof review.submitted_at === 'string' && review.submitted_at.length > 0
    && typeof review.body === 'string'
    && review.body.split(/\r?\n/).includes('Approval-Record-SHA256: ' + r.reviewBindingSha256);
}

export async function githubReview(r) {
  const token = process.env.GITHUB_TOKEN;
  const repo = process.env.GITHUB_REPOSITORY;
  if (!token || !/^[\w.-]+\/[\w.-]+$/.test(repo ?? '')) throw Error('GitHub authenticated review lookup unavailable');
  const readJson = async (suffix) => {
    const response = await fetch('https://api.github.com/repos/' + repo + '/pulls/' + r.reviewedPr + suffix, {
      headers: {
        Authorization: 'Bearer ' + token,
        Accept: 'application/vnd.github+json',
        'X-GitHub-Api-Version': '2022-11-28',
        'User-Agent': 'kingshot-native-review-provenance',
      },
    });
    if (!response.ok) throw Error('GitHub review/PR lookup: HTTP ' + response.status);
    return response.json();
  };
  const [review, pr] = await Promise.all([readJson('/reviews/' + r.reviewId), readJson('')]);
  return reviewEvidenceMatches(r, review, pr);
}

export async function check(r, exported, manifest, reviewVerifier = githubReview) {
  validate(r);
  assignedReviewer(r, manifest);
  const v = values(r, exported);
  if (hash(v.source) !== r.sourceSha256 || hash(v.translation) !== r.translationSha256) throw Error('stale translation/source: ' + id(r));
  if (await reviewVerifier(r) !== true) throw Error('missing matching CURRENT HEAD GitHub APPROVED review: ' + id(r));
  return id(r);
}

export function bootstrapStatus(provisionalMessages, verifiedRecords, localeCount = localeCodes.size) {
  if (!Number.isSafeInteger(provisionalMessages) || provisionalMessages < 0
    || !Number.isSafeInteger(verifiedRecords) || verifiedRecords < 0
    || verifiedRecords > provisionalMessages * localeCount) throw Error('invalid review coverage counters');
  return {
    status: 'REVIEW_REQUIRED',
    reviewedNativeApprovals: verifiedRecords,
    provisionalScreenedEnglishKeys: provisionalMessages,
    locales: localeCount,
    provisionalReviewUnits: provisionalMessages * localeCount,
    reviewRequired: provisionalMessages * localeCount - verifiedRecords,
    frozenTier1ScopeApproved: false,
    nativeTranslationReleaseApproved: false,
    message: 'Integrity check passes without reviewers; all provisional Tier 1 screen candidates remain review_required until independently approved. Counts are screening estimates, not a frozen release manifest.',
  };
}

export async function main(root = process.cwd()) {
  const directory = path.join(root, 'resources/data/localization/reviews');
  const names = existsSync(directory) ? readdirSync(directory).filter((n) => n.endsWith('.json')).sort() : [];
  const exported = (n) => JSON.parse(readFileSync(path.join(root, '.translation-pr6-current', n), 'utf8'));
  const data = {
    client: exported('effective-all-locales.json'),
    server: exported('server-catalogues.json'),
    php: exported('server-php-catalogues.json'),
  };
  const assignments = JSON.parse(readFileSync(path.join(root, 'resources/data/localization/reviewer-assignments.json'), 'utf8'));
  const risk = exported('semantic-risk-inventory.json');
  if (!Array.isArray(risk.flagged)) throw Error('missing semantic-risk inventory');
  const provisionalKeys = new Set(risk.flagged.filter((entry) => strictReasons(entry.source).length > 0)
    .map((entry) => [entry.catalogue, entry.domain, entry.key].join(':')));
  const seen = new Set();
  let verifiedCandidateRecords = 0;
  for (const name of names) {
    const r = JSON.parse(readFileSync(path.join(directory, name), 'utf8'));
    validate(r);
    if (name !== hash(id(r)) + '.json' || seen.has(id(r))) throw Error('duplicate or incorrectly named approval: ' + id(r));
    seen.add(id(r));
    await check(r, data, assignments);
    if (provisionalKeys.has([r.catalogue, r.domain, r.key].join(':'))) verifiedCandidateRecords++;
  }
  const summary = {
    ...bootstrapStatus(provisionalKeys.size, verifiedCandidateRecords),
    totalVerifiedRecordClaims: seen.size,
    unverifiedCandidateRecords: provisionalKeys.size * localeCodes.size - verifiedCandidateRecords,
  };
  console.log('NATIVE PROVENANCE BOOTSTRAP: ' + JSON.stringify(summary));
}

if (process.argv[1] && import.meta.url===pathToFileURL(path.resolve(process.argv[1])).href) {
  main().catch(e=>{console.error(e);process.exitCode=1;});
}
