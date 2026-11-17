import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

// Explicitly exploratory, not an approval or a release blocker. Broad regexes
// identify candidates; domain owners must review omissions and false positives.
export const tier1aRules = Object.freeze([
  { category: 'hard-quantity-constraints', match: /\b(at most|at least|no more than|no fewer than|maximum|minimum|exceed(?:s|ed)?|between|must retain|cannot contain|only once)\b/i },
  { category: 'account-recovery-and-security', match: /\b(password|passkey|recover(?:y|ies)|reset|two-factor|email change|account (?:deletion|removal)|security (?:notice|alert)|sessions? invalidated)\b/i },
  { category: 'destructive-actions', match: /\b(delete|deletion|revoke|revoked|unlink|disconnect|disable|purge|permanently|irreversible|destroy|erase)\b/i },
  { category: 'authorization-restrictions', match: /\b(access denied|not allowed|cannot|must not|prohibited|forbidden|permission|unauthorized|only (?:an?|the) (?:admin|owner|governor)|lacks? (?:permission|access)|not permitted)\b/i },
  { category: 'eligibility-expiry', match: /\b(ineligible|eligibility|not eligible|transfer (?:blocked|deadline|limit)|cannot transfer|not (?:verified|approved)|expires? (?:in|after|at))\b/i },
]);

export function strictReasons(source) {
  return tier1aRules.filter(({ match }) => match.test(source)).map(({ category }) => category);
}

export function buildStrictReport(flagged) {
  const entries = flagged
    .map((entry) => ({ ...entry, strictReasons: strictReasons(entry.source) }))
    .filter((entry) => entry.strictReasons.length > 0);
  const perCategory = Object.fromEntries(tier1aRules.map(({ category }) => [
    category, entries.filter((entry) => entry.strictReasons.includes(category)).length,
  ]));
  const perDomain = {};
  const perCatalogue = {};
  for (const entry of entries) {
    const key = entry.catalogue + ':' + entry.domain;
    perDomain[key] = (perDomain[key] ?? 0) + 1;
    perCatalogue[entry.catalogue] = (perCatalogue[entry.catalogue] ?? 0) + 1;
  }
  return {
    note: 'Exploratory high-sensitivity keyword screen, NOT a complete Tier 1a list or a linguistically approved inventory. Categories overlap.',
    candidates: entries.length,
    broadFlagged: flagged.length,
    perCategory,
    perDomain: Object.fromEntries(Object.entries(perDomain).sort()),
    perCatalogue,
    entries,
  };
}

if (process.argv[1] && path.basename(process.argv[1]) === 'report-localization-tier1a.mjs') {
  const source = process.argv[2] ?? '.translation-pr1-baseline/semantic-risk-inventory.json';
  const output = process.argv[3] ?? '.translation-pr1-baseline/tier1a-exploratory.json';
  const { flagged } = JSON.parse(fs.readFileSync(source, 'utf8'));
  const report = buildStrictReport(flagged);
  fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify({
    candidates: report.candidates,
    perCatalogue: report.perCatalogue,
    perCategory: report.perCategory,
    perDomain: report.perDomain,
    disclaimer: report.note,
  }, null, 2));
}
