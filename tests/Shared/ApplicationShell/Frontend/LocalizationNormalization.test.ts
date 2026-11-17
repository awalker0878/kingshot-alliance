import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { normalizeLocale, selectFirstSupportedLocale } from '../../../../resources/js/localization/locales.ts';

const fixture = JSON.parse(readFileSync('resources/data/localization/locale-normalization-cases.json', 'utf8')) as {
  cases: Array<{ input: string | null; expected: string | null }>;
  priority: Array<{ candidates: Array<string | null>; expected: string }>;
};

test('client locale normalizer matches the shared PHP and browser-language fixture', () => {
  for (const { input, expected } of fixture.cases)
    assert.equal(normalizeLocale(input), expected, JSON.stringify(input));
});

test('first valid language preference wins in caller-defined order', () => {
  for (const { candidates, expected } of fixture.priority)
    assert.equal(selectFirstSupportedLocale(candidates), expected, JSON.stringify(candidates));
});
