import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';

const inventory = readFileSync('docs/frontend/localization-pr3-recipient-collision-winners.md', 'utf8');
const followup = readFileSync('docs/frontend/localization-pr3-recipient-review-required.md', 'utf8');
const keys = inventory.split('\\n').filter((line) => line.startsWith('| \`notifications.')).map((line) => line.split('\\`')[1]);
assert.equal(keys.length, 41, 'must cover all differing-value collision winners');
assert.equal(new Set(keys).size, 41, 'duplicate recipient key');
const reviewKeys = followup.split('\\n').filter((line) => line.startsWith('| \`notifications.')).map((line) => line.split('\\`')[1]);
assert.deepEqual(reviewKeys, keys, 'review_required list must have all 41 exact winner keys, same order');
const all = JSON.parse(readFileSync('.translation-pr3-current/effective-all-locales.json', 'utf8')).account;
const languages = ['fr', 'es', 'ar'];
const placeholder = (s) => [...s.matchAll(/\\{([^}]+)\\}/g)].map((m) => m[1]).sort();
const reports = keys.map((key) => {
  const english = all.en[key];
  assert.equal(typeof english, 'string', key + ': missing English effective value');
  const localized = Object.fromEntries(languages.map((loc) => {
    const translated = all[loc][key];
    assert.equal(typeof translated, 'string', loc + ': missing ' + key);
    assert.deepEqual(placeholder(translated), placeholder(english), loc + ': interpolation tokens differ for ' + key);
    return [loc, translated];
  }));
  return { key, status: 'review_required', en: english, translations: localized };
});
writeFileSync('.translation-pr3-current/recipient-language-review.json', JSON.stringify({ source: 'effective runtime', supportedLanguages: languages, semanticStatus: 'review_required', entries: reports }, null, 2) + '\\n');
console.log('PR3 RECIPIENT LANGUAGE FOLLOW-UP: 41 keys present in French, Spanish and Arabic; all placeholder sets preserved; no native-language approval claimed');
