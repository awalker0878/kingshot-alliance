import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { buildStrictReport } from './report-localization-tier1a.mjs';

// The original 511-candidate screen stays intact. V2 unions its results with
// new key-aware rules run against the FULL pinned English catalogue, not merely
// the 1,985 messages selected by the first broad-text screening pass.
const root = process.argv[2] ?? '.translation-pr1-baseline';
const dest = process.argv[3] ?? '.translation-pr1-baseline/tier1a-v2-exploratory.json';
const manifest = JSON.parse(fs.readFileSync('resources/data/localization/baseline-fingerprints.json', 'utf8'));
assert.equal(manifest.baselineRef, '7cf387b628bbbaeb81d10120340e559b46403bee');

const pinnedInputs = [
  'semantic-risk-inventory.json',
  'effective-en.json',
  'server-catalogues.json',
  'server-php-catalogues.json',
];
const inputSha = {};
for (const name of pinnedInputs) {
  const bytes = fs.readFileSync(path.join(root, name));
  const sha = createHash('sha256').update(bytes).digest('hex');
  assert.equal(sha, manifest.pinned[name], name + ': pinned input drift');
  inputSha[name] = sha;
}
const additionalPinned = {
  'risk-unflagged-samples.json': '8c77a3d7844889e99427e6d6cb2f2a47b54e526791f02461d97ef9772af35de3',
  'risk-sample-metrics.json': 'ff868b4c8b3208b3f2abf6a11b55814c9d4632eecacd6d8db426d9f58f18d77d',
};
for (const [name, sha] of Object.entries(additionalPinned)) {
  const actual = createHash('sha256').update(fs.readFileSync(path.join(root, name))).digest('hex');
  assert.equal(actual, sha, name + ': pinned review sample drift');
  inputSha[name] = actual;
}
const read = (name) => JSON.parse(fs.readFileSync(path.join(root, name), 'utf8'));
const broad = read('semantic-risk-inventory.json');
const baseline = buildStrictReport(broad.flagged);
assert.equal(broad.flagged.length, 1985);
assert.equal(baseline.candidates, 511);
const client = read('effective-en.json');
const server = read('server-catalogues.json').en;
const accounts = read('server-php-catalogues.json').en['accounts.php'];
const complete = [];
for (const [domain, messages] of Object.entries(client))
  for (const [key, source] of Object.entries(messages))
    complete.push({ catalogue: 'client', domain, key, source });
for (const [key, source] of Object.entries(server))
  complete.push({ catalogue: 'server', domain: 'json', key, source });
for (const item of accounts)
  complete.push({ catalogue: 'server', domain: 'accounts', key: item.key, source: item.value });
assert.equal(complete.length, 6812, 'all English source messages must be scanned');

const supplementalRules = Object.freeze([
  {
    category: 'state-transition-words',
    key: /(?:^|\.)(?:statuses|status|states|state)\.(?:pending|queued|sending|processing|inProgress|failed|cancelled|completed|delivered|blocked)$/i,
    source: /\b(pending|queued|sending|processing|in progress|failed|cancelled|completed|delivered|blocked|ready)\b/i,
  },
  {
    category: 'security-claims',
    key: /(?:^|\.)(?:deliverySecurity|security|securityClaim|credentials?|secret|token|privacy)(?:\.|[A-Z]|$)/i,
    source: /\b(encrypt(?:ed|ion)?|credentials?|never (?:returned|exposed|shared|shown|stored)|scop(?:e|ed|ing) to|confidential|private|securely|security|browser)\b/i,
  },
  {
    category: 'confirmation-action',
    key: /(?:^|\.)(?:confirm[A-Z][\w]*|confirm|confirmDescription|confirmTitle|bulk\.(?:confirm|previewSummary|resultSummary))(?:\.|$)/i,
    source: /\b(apply|change|confirm|delete|remove|restore|transfer|revoke|reset|overwrite|publish|archive|selected|messages?|items?|operation|count|blocked|ready|skipped)\b|\{(?:operation|count)\}/i,
  },
  {
    category: 'expiry-state',
    key: /(?:expiry|expir(?:ing|ation|ed|es?))/i,
    source: /\b(expir(?:ing|ation|ed|es?)|expiry|deadline)\b/i,
  },
]);
const supplementalReasons = (entry) => supplementalRules
  .filter((rule) => rule.key.test(entry.key) && rule.source.test(entry.source))
  .map((rule) => rule.category);
const keyFor = (e) => [e.catalogue, e.domain, e.key].join('\u0000');
const baselineIds = new Set(baseline.entries.map(keyFor));
assert.equal(baselineIds.size, 511);
const union = [];
const addedByRule = Object.fromEntries(supplementalRules.map((rule) => [rule.category, 0]));
for (const entry of complete) {
  const source = keyFor(entry);
  const originalSelected = baselineIds.has(source);
  const supplemental = supplementalReasons(entry);
  if (!originalSelected && supplemental.length === 0) continue;
  if (!originalSelected)
    for (const reason of supplemental) addedByRule[reason]++;
  union.push({ ...entry, selectedByOriginal511: originalSelected,
    supplementalReasons: supplemental, reviewStatus: 'review_required' });
}
assert.equal(union.length, 613, 'revised pinned candidate count changed; review the rules');
assert.equal(union.filter((x) => !x.selectedByOriginal511).length, 102);
const ids = new Set(union.map(keyFor));
assert.equal(ids.size, 613, 'the two-stage union must not double-count');
for (const key of [
  'notifications.statuses.pending',
  'notifications.bulk.confirmDescription',
  'notifications.bulk.previewSummary',
  'notifications.deliverySecurity',
  'notifications.types.giftCodeExpiring',
]) {
  assert.ok(union.some((x) => x.catalogue === 'client' && x.domain === 'account'
    && x.key === key && x.supplementalReasons.length),
  'new rule did not capture manually surfaced risk: ' + key);
}
const samples = read('risk-unflagged-samples.json').samples;
const metrics = read('risk-sample-metrics.json');
const perLocale = {};
for (const [locale, entries] of Object.entries(samples)) {
  assert.equal(entries.length, 32);
  assert.equal(metrics[locale].sampled, 32);
  assert.equal(metrics[locale].reviewed, 0, 'native calibration status is no longer unknown');
  const preselected = entries.filter((item) => supplementalReasons(item).length > 0);
  perLocale[locale] = {
    sampled: 32, humanReviewed: 0, humanUnreviewed: 32,
    sampledNewRuleMatches: preselected.length,
    sampledNewRuleMatchKeys: preselected.map((x) => x.catalogue + ':' + x.domain + ':' + x.key),
    confirmedTier1Misses: null,
    estimatedMissRate: null,
    status: 'REVIEW_REQUIRED',
  };
}
assert.equal(Object.keys(perLocale).length, 16);
const perCatalogue = {};
const perDomain = {};
for (const item of union) {
  perCatalogue[item.catalogue] = (perCatalogue[item.catalogue] ?? 0) + 1;
  const domainKey = item.catalogue + ':' + item.domain;
  perDomain[domainKey] = (perDomain[domainKey] ?? 0) + 1;
}
const report = {
  schema: 1,
  pinnedBaseRef: manifest.baselineRef,
  pinnedInputsSha256: inputSha,
  fullEnglishMessageCount: complete.length,
  broadFlaggedCount: broad.flagged.length,
  priorStrictCandidates: 511,
  revisedExploratoryCandidates: union.length,
  newlySurfacedCandidates: 102,
  perCatalogue, perDomain, addedByRule,
  additionalScreeningRules: supplementalRules.map((r) => ({
    category: r.category, keyPattern: r.key.source, sourcePattern: r.source.source,
  })),
  perLocaleCalibration: perLocale,
  calibrationStatus: '512 of 512 samples require human review; sample false-negative rates are unknown, not zero.',
  limitations: [
    'The count is exploratory; only a qualified domain/security owner can classify true Tier 1 cases.',
    'The revised screen examines every pinned English message, not just broadly flagged entries.',
    'The source sample is deterministic but not an independently adjudicated false-negative estimate.',
    'The 102 additions have not yet been approved for mandatory locale-by-locale release scope.',
  ],
  candidates: union,
};
fs.mkdirSync(path.dirname(dest), { recursive: true });
fs.writeFileSync(dest, JSON.stringify(report, null, 2) + '\n');
console.log('TIER1 V2 PINNED REPLAY: ' + JSON.stringify({
  originals: 511, revised: union.length, added: 102,
  perCatalogue, addedByRule,
  localeSamples: Object.fromEntries(Object.entries(perLocale).map(([k, v]) =>
    [k, { reviewed: v.humanReviewed, sampled: v.sampled, estimatedMissRate: v.estimatedMissRate,
      ruleMatches: v.sampledNewRuleMatches }])),
}));
