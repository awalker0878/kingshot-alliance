import assert from 'node:assert/strict';
import test from 'node:test';
import { stableReviewIdentity } from '../../../../scripts/localization/stable-review-identity.mjs';

const entry = {
  catalogue: 'server',
  domain: 'accounts',
  key: 'accounts.php#1',
  source: 'Continue',
};
const bridge = {
  sourceRef: '7cf387b628bbbaeb81d10120340e559b46403bee',
  legacyAccounts: {
    'accounts.php#1': { key: 'accounts.verify.action', source: 'Continue' },
    'accounts.php#2': { key: 'accounts.reset.action', source: 'Continue' },
  },
};

test('the pinned identity bridge retains different keys with identical English text', () => {
  assert.equal(stableReviewIdentity(entry, bridge).key, 'accounts.verify.action');
  assert.equal(
    stableReviewIdentity({ ...entry, key: 'accounts.php#2' }, bridge).key,
    'accounts.reset.action',
  );
});

test('another revision, missing ordinal or mismatched source cannot bind a durable review', () => {
  assert.throws(
    () => stableReviewIdentity(entry, { ...bridge, sourceRef: 'a'.repeat(40) }),
    /pinned source/,
  );
  assert.throws(
    () => stableReviewIdentity({ ...entry, key: 'accounts.php#3' }, bridge),
    /Missing stable/,
  );
  assert.throws(
    () => stableReviewIdentity({ ...entry, source: 'Changed source' }, bridge),
    /source\/identity mismatch/,
  );
});
