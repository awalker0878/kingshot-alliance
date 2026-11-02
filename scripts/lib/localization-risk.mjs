import { createHash } from 'node:crypto';

// Conservative candidate detection. A missing match is not evidence of safety.
// Classification is followed by domain-owner review and sampling of non-flagged entries.
export const riskRules = Object.freeze([
  { id: 'quantities-and-limits', pattern: /\b(at most|at least|no more than|no fewer than|more than|less than|maximum|minimum|between|up to|exceeds?|must retain|cannot contain|only once)\b/i },
  { id: 'permission-and-denial', pattern: /\b(permission|permitted|authorized|authorization|access denied|not allowed|cannot|must not|prohibited|forbidden|requires?|required|eligible|ineligible|only if)\b/i },
  { id: 'security-and-ownership', pattern: /\b(password|passkey|recovery|verification|verify|security|two-factor|account|ownership|unlink|delete|deletion|revoke|revoked|identity|credential|sign.in)\b/i },
  { id: 'state-and-outcome', pattern: /\b(blocked|blocker|failed|failure|pending|cancelled|canceled|completed|successful|succeeded|unavailable|available|expired|expiry|expires|rejected|approved)\b/i },
  { id: 'game-eligibility-and-timing', pattern: /\b(transfer|deadline|window|eligibility|gift code|redemption|redeem|leadership|rank|administrator|role)\b/i },
]);

export function riskReasons(source) {
  if (typeof source !== 'string') return [];
  return riskRules.filter(({ pattern }) => pattern.test(source)).map(({ id }) => id);
}

export function riskEntry(catalogue, domain, key, source) {
  return { catalogue, domain, key, source, reasons: riskReasons(source) };
}

export function stableUnflaggedSample(entries, locale, size = 32, seed = '') {
  if (!Number.isSafeInteger(size) || size < 0) throw new Error('Invalid sample size');
  return entries
    .filter((entry) => entry.reasons.length === 0)
    .map((entry) => ({
      entry,
      order: createHash('sha256')
        .update([seed, locale, entry.catalogue, entry.domain, entry.key].join('\0'))
        .digest('hex'),
    }))
    .sort((a, b) => a.order.localeCompare(b.order))
    .slice(0, size)
    .map(({ entry }) => entry);
}
