import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';

// This structural migration deliberately changes source layout, not effective
// runtime text. Keep the original PR1 hashes pinned; never rewrite the manifest
// to make an unreviewed catalogue change look green.
const manifest=JSON.parse(readFileSync('resources/data/localization/baseline-fingerprints.json','utf8'));
const previous=readFileSync(process.env.LOCALIZATION_POSTMERGE_PINNED_RAW ?? '.translation-pr1-baseline/raw-catalogues.json');
const current=readFileSync(process.env.LOCALIZATION_POSTMERGE_CURRENT_RAW ?? '.translation-pr3-current/raw-catalogues.json');
const sha=(data)=>createHash('sha256').update(data).digest('hex');
assert.equal(sha(previous),manifest.pinned['raw-catalogues.json'],'Original raw-source baseline changed');
const expectedCurrent='2ab071d65edd083aadd2f3707dffcf2f1490f38ded8aa87bb8a59cf401f2f834';
// Validate the explicit domain/locale scope before comparing the exact approved SHA.
const before=JSON.parse(previous),after=JSON.parse(current);
assert.deepEqual(Object.keys(before).sort(),Object.keys(after).sort(),'Domain set changed');
const changed=[];
for(const domain of Object.keys(before)){
  assert.deepEqual(Object.keys(before[domain]).sort(),Object.keys(after[domain]).sort(),'Locale set changed for '+domain);
  for(const locale of Object.keys(before[domain])){
    if(JSON.stringify(before[domain][locale])!==JSON.stringify(after[domain][locale]))changed.push(domain+':'+locale);
  }
}
assert.deepEqual(changed.sort(),['account:en','alliance:en','assistant:en','core:en','events:en','kingdom:en','progression:en','territory:en','transfers:en'],'Unexpected raw source domain/locale changes');
assert.equal(sha(current),expectedCurrent,'Post-merge structural source hash differs from approved PR3 migration');
const report={
  mergedTargetSha:process.env.GITHUB_SHA??null,
  pinnedRawSha256:sha(previous),
  approvedMergedRawSha256:sha(current),
  changedRawDomainLocales:changed,
  expectedStructuralChanges:['account:en','alliance:en','assistant:en','core:en','events:en','kingdom:en','progression:en','territory:en','transfers:en'],
  effectiveValuesCheckedSeparately:'six SHA-256 assertions in check-localization-pr3-parity.mjs',
  note:'Raw-source bytes changed intentionally; no baseline manifest hash was rewritten.',
};
writeFileSync('.translation-pr3-current/postmerge-source-delta.json',JSON.stringify(report,null,2)+'\n');
console.log('PR3 POSTMERGE RAW-SOURCE CHANGES: '+JSON.stringify(report));
