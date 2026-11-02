import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { createServer } from 'vite';
import { flattenMessages } from './lib/localization-completeness.mjs';
import { riskEntry, stableUnflaggedSample } from './lib/localization-risk.mjs';

const output = path.resolve(process.argv[2] ?? '.translation-pr1-baseline');
const sampleCount = Number(process.env.LOCALIZATION_RISK_SAMPLE_SIZE ?? 32);
if (!Number.isSafeInteger(sampleCount) || sampleCount < 1) {
  throw new Error('LOCALIZATION_RISK_SAMPLE_SIZE must be a positive integer.');
}
const head = execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim();
const readJson = async (file) => JSON.parse(await fs.readFile(file, 'utf8'));
const hash = (value) => createHash('sha256').update(value).digest('hex');
await fs.mkdir(output, { recursive: true });

const vite = await createServer({
  configFile: false,
  root: process.cwd(),
  server: { middlewareMode: true },
  appType: 'custom',
});
let domains, locales, raw, effective;
try {
  const registry = await vite.ssrLoadModule('/resources/js/localization/registry.ts');
  domains = registry.localizationDomains;
  ({ localeCodes: locales } = await vite.ssrLoadModule('/resources/js/localization/locales.ts'));
  const { loadCatalogue } = await vite.ssrLoadModule('/resources/js/localization/loader.ts');
  raw = {};
  effective = {};
  for (const domain of domains) {
    raw[domain] = {};
    effective[domain] = {};
    for (const locale of locales) {
      raw[domain][locale] = flattenMessages(
        (await registry.importDomainCatalogue(domain, locale)).default,
      );
      effective[domain][locale] = flattenMessages(await loadCatalogue(domain, locale));
    }
  }
} finally {
  await vite.close();
}

const server = {};
for (const locale of locales) {
  server[locale] = await readJson(path.join('lang', locale + '.json'));
}
const englishAccounts = await fs.readFile('lang/en/accounts.php', 'utf8');
const phpLiteral = /=>\s*('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")\s*,/g;
const decodePhp = (literal) => literal.slice(1, -1).replace(/\\(['"\\])/g, '$1');
const accountTranslations = {};
for (const locale of locales.filter((code) => code !== 'en')) {
  const localized = await fs.readFile(path.join('lang', locale, 'accounts.php'), 'utf8');
  accountTranslations[locale] = [...localized.matchAll(phpLiteral)].map((match) => decodePhp(match[1]));
}
const accounts = [...englishAccounts.matchAll(phpLiteral)].map((match, index) => ({
  catalogue: 'server',
  domain: 'accounts',
  key: 'accounts.php#' + (index + 1),
  source: decodePhp(match[1]),
}));

const conflicts = [];
const messageIndex = new Map();
for (const domain of domains) {
  const base = raw[domain].en;
  const merged = effective[domain].en;
  for (const [key, source] of Object.entries(merged)) {
    if (!(key in base) || source !== base[key]) {
      conflicts.push({
        domain,
        key,
        original: base[key] ?? null,
        winner: source,
        kind: key in base ? 'overridden' : 'overlay-added',
      });
    }
    const matches = messageIndex.get(key) ?? [];
    matches.push({ domain, value: source });
    messageIndex.set(key, matches);
  }
}
const duplicatedKeys = [...messageIndex.entries()]
  .filter(([, values]) => values.length > 1)
  .map(([key, occurrences]) => ({ key, occurrences }))
  .sort((a, b) => a.key.localeCompare(b.key));
conflicts.sort((a, b) => (a.domain + ':' + a.key).localeCompare(b.domain + ':' + b.key));

const entries = [];
for (const domain of domains) {
  for (const [key, source] of Object.entries(effective[domain].en)) {
    entries.push(riskEntry('client', domain, key, source));
  }
}
for (const source of Object.keys(server.en)) {
  entries.push(riskEntry('server', 'json', source, source));
}
for (const message of accounts) {
  entries.push(riskEntry(message.catalogue, message.domain, message.key, message.source));
}
const flagged = entries.filter((entry) => entry.reasons.length > 0);
const samples = {};
for (const locale of locales.filter((code) => code !== 'en')) {
  samples[locale] = stableUnflaggedSample(entries, locale, sampleCount, head)
    .map((entry) => ({
      ...entry,
      translation: entry.catalogue === 'client'
        ? effective[entry.domain][locale]?.[entry.key] ?? null
        : entry.domain === 'json'
          ? server[locale]?.[entry.key] ?? null
          : accountTranslations[locale]?.[Number(entry.key.split('#')[1]) - 1] ?? null,
      auditStatus: 'unreviewed',
      reviewOutcome: null,
    }));
}

const phpFiles = {};
for (const locale of locales) {
  const directory = path.join('lang', locale);
  const filenames = await fs.readdir(directory).catch(() => []);
  phpFiles[locale] = {};
  for (const filename of filenames.filter((name) => name.endsWith('.php')).sort()) {
    phpFiles[locale][filename] = hash(await fs.readFile(path.join(directory, filename)));
  }
}

let build = { status: 'missing', note: 'Run npm run build before this exporter to capture emitted Vite assets.' };
try {
  const manifest = await readJson('public/build/manifest.json');
  const chunks = [];
  for (const entry of Object.values(manifest)) {
    if (!entry.file?.endsWith('.js')) continue;
    chunks.push({
      source: entry.src ?? null,
      file: entry.file,
      bytes: (await fs.stat(path.join('public/build', entry.file))).size,
      isLocale: Boolean(entry.src?.startsWith('resources/js/localization/messages/')),
      isPage: Boolean(entry.src?.startsWith('resources/js/pages/')),
    });
  }
  chunks.sort((a, b) => (a.source ?? a.file).localeCompare(b.source ?? b.file));
  const largestPage = chunks.filter((chunk) => chunk.isPage).sort((a, b) => b.bytes - a.bytes)[0];
  build = { status: 'captured', largestPage: largestPage ?? null, chunks };
} catch (error) {
  build.note = 'Cannot capture production assets: ' + String(error);
}

const report = {
  version: 1,
  baselineCommit: head,
  capturedAt: new Date().toISOString(),
  domains,
  locales,
  clientSourceEntries: entries.filter((entry) => entry.catalogue === 'client').length,
  serverJsonSourceEntries: Object.keys(server.en).length,
  serverAccountsSourceEntries: accounts.length,
  candidateTier1Entries: flagged.length,
  unflaggedEntries: entries.length - flagged.length,
  perLocaleCandidateReviewUnits: Object.fromEntries(
    locales.filter((code) => code !== 'en').map((locale) => [locale, flagged.length]),
  ),
  nonFlaggedSamplePerLocale: sampleCount,
  reviewDisclaimer: 'Keyword rules produce candidates, not an exhaustive risk inventory or linguistic approvals.',
  frameworkNote: 'Framework auth/validation/password PHP strings are inventoried by file hash; full source-key review is a subsequent task against the locked Laravel vendor version.',
};
const files = {
  'baseline-metadata.json': report,
  'raw-catalogues.json': raw,
  'effective-all-locales.json': effective,
  'effective-en.json': Object.fromEntries(domains.map((domain) => [domain, effective[domain].en])),
  'server-catalogues.json': server,
  'server-php-hashes.json': phpFiles,
  'overlay-conflicts.json': { mergesOrAdditions: conflicts, duplicateAcrossDomains: duplicatedKeys },
  'semantic-risk-inventory.json': { flagged, total: entries.length },
  'risk-unflagged-samples.json': {
    instructions: 'A qualified reviewer records whether each sampled unflagged entry is actually Tier 1. For each locale, miss-rate estimate = promoted-to-Tier-1 / reviewed sample count; report confidence and possible selection bias.',
    samples,
  },
  'vite-chunks.json': build,
};
for (const [name, data] of Object.entries(files)) {
  await fs.writeFile(path.join(output, name), JSON.stringify(data, null, 2) + '\n');
}
console.log(JSON.stringify({
  output,
  commit: head,
  ...report,
  overlayMergeOrAdditionCount: conflicts.length,
  crossDomainDuplicateKeys: duplicatedKeys.length,
  buildStatus: build.status,
}, null, 2));
