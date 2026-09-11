import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { localeCodes } from '../../../../../resources/js/localization/locales.ts';

for (const locale of localeCodes) {
  test(`Transfer boolean facts have distinct translated labels in ${locale}`, async () => {
    const { default: catalogue } = await import(
      `../../../../../resources/js/localization/messages/core/${locale}.ts`
    );
    for (const key of ['yes', 'no']) {
      assert.equal(typeof catalogue.common[key], 'string', `${locale}: common.${key}`);
      assert.ok(catalogue.common[key].trim().length > 0);
      assert.notEqual(catalogue.common[key], `common.${key}`);
    }
    assert.notEqual(catalogue.common.yes, catalogue.common.no);
  });
}

for (const path of [
  'resources/js/pages/Kingdom/Transfer/Readiness.vue',
  'resources/js/components/transfers/TransferEvidencePanel.vue',
]) {
  test(`common labels used by ${path} resolve in the real fallback catalogue`, async () => {
    const source = readFileSync(new URL(`../../../../../${path}`, import.meta.url), 'utf8');
    const { default: core } =
      await import('../../../../../resources/js/localization/messages/core/en.ts');
    const references = [...source.matchAll(/\bt\('common\.([^']+)'/g)].map((match) => match[1]);
    assert.ok(references.length >= 2, 'The real boolean presentation must supply its labels.');
    for (const key of references) {
      assert.equal(typeof (core.common as Record<string, unknown>)[key], 'string', key);
    }
  });
}
