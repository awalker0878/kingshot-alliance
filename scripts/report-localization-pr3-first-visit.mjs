import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const baseline = 'a5151e717020cfe00a32aeddcca6279f9c7042fe';
const oldLoader = execFileSync('git', ['show', baseline + ':resources/js/localization/loader.ts'], { encoding: 'utf8' });
const currentLoader = readFileSync('resources/js/localization/loader.ts', 'utf8');
const pageRegistry = readFileSync('resources/js/localization/registry.ts', 'utf8');
const before = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const after = JSON.parse(readFileSync(process.argv[3], 'utf8'));
const chunk = (build, source) => {
  const result = build.chunks.filter((x) => x.source === source);
  assert.equal(result.length, 1, 'missing or duplicate Vite chunk: ' + source);
  return result[0].bytes;
};
const scenarios = [
  {
    entryPage: 'Accounts/Notifications/Index',
    pagePrefix: "name.startsWith('Accounts/Notifications/')",
    domain: 'account',
    overlay: 'resources/js/localization/communications-recipient-delivery-labels.ts',
    oldLoaderCall: "if (domain === 'account') catalogue = await communicationsRecipientDeliveryCatalogue(catalogue);",
    oldDynamicImport: "await import('./communications-recipient-delivery-labels')",
  },
  {
    entryPage: 'Kingdom/Transfer/Index',
    pagePrefix: "name.startsWith('Kingdom/Transfer/')",
    domain: 'transfers',
    overlay: 'resources/js/localization/transfer-evidence-labels.ts',
    oldLoaderCall: "if (domain === 'transfers') catalogue = await transfersCatalogue(catalogue);",
    oldDynamicImport: "await import('./transfer-evidence-labels')",
  },
];
const rows = scenarios.map((entry) => {
  assert.ok(pageRegistry.includes(entry.pagePrefix), 'page no longer maps to its domain');
  assert.ok(oldLoader.includes(entry.oldLoaderCall), 'original overlay not loaded for whole domain');
  assert.ok(oldLoader.includes(entry.oldDynamicImport), 'original overlay not dynamically loaded');
  assert.ok(!currentLoader.includes(entry.oldDynamicImport), 'old overlay still in current loader');
  const source = 'resources/js/localization/messages/' + entry.domain + '/en.ts';
  const oldDomainBytes = chunk(before, source);
  const oldOverlayBytes = chunk(before, entry.overlay);
  const newDomainBytes = chunk(after, source);
  assert.ok(!after.chunks.some((item) => item.source === entry.overlay));
  // The English domain is loaded even for translated page views as the fallback.
  assert.match(currentLoader, /await loadCatalogue\(domain, defaultLocale\)/);
  const beforeBytes = oldDomainBytes + oldOverlayBytes;
  const afterBytes = newDomainBytes;
  return {
    entryPage: entry.entryPage, localeFallback: 'English is always loaded for domain fallback',
    oldDomainBytes, oldOverlayBytes, beforeBytes, afterBytes,
    marginalUncompressedBytes: afterBytes - beforeBytes,
    percentChange: Number((((afterBytes - beforeBytes) / beforeBytes) * 100).toFixed(2)),
    evidence: 'Both original files were loaded together on any first domain request, not one overlay per special page.',
  };
});
assert.equal(rows[0].marginalUncompressedBytes, 92);
assert.equal(rows[1].marginalUncompressedBytes, 12);
const report = {
  baselineRef: baseline, sourceOfTruth: 'source-mapped production Vite output and loader domain routing',
  rows, caveats: [
    'Uncompressed, source-attributed bytes; not a measured compressed network waterfall.',
    'Does not assume other unrelated page chunks or cross-route cache entries are cold.',
    'The shared app entry is 545 bytes smaller in the same build; not folded into per-domain costs.',
  ],
};
writeFileSync('.translation-pr3-current/first-entry-domain-costs.json', JSON.stringify(report, null, 2) + '\n');
console.log('PR3 FIRST DOMAIN VISIT COSTS: ' + JSON.stringify(rows));
