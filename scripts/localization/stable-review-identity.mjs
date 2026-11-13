import assert from 'node:assert/strict';

export function stableReviewIdentity(entry, php) {
  if (entry.catalogue !== 'server' || entry.domain !== 'accounts') return entry;
  assert.equal(
    php.sourceRef,
    '7cf387b628bbbaeb81d10120340e559b46403bee',
    'Identity bridge must use pinned source',
  );
  const mapped = php.legacyAccounts[entry.key];
  assert.ok(mapped, 'Missing stable PHP key for ' + entry.key);
  assert.equal(mapped.source, entry.source, 'Legacy source/identity mismatch: ' + entry.key);
  return { ...entry, key: mapped.key, legacyKey: entry.key };
}
