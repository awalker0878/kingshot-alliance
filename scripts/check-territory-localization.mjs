import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import process from 'node:process';
import { createServer } from 'vite';
import { flattenMessages } from './lib/localization-completeness.mjs';

const server = await createServer({
  configFile: false,
  root: process.cwd(),
  server: { middlewareMode: true },
  appType: 'custom',
});
try {
  const { localeCodes } = await server.ssrLoadModule('/resources/js/localization/locales.ts');
  const { importDomainCatalogue } = await server.ssrLoadModule(
    '/resources/js/localization/registry.ts',
  );
  const { loadCatalogue } = await server.ssrLoadModule('/resources/js/localization/loader.ts');
  const expected = Object.keys(flattenMessages(await loadCatalogue('territory', 'en'))).sort();
  const locales = localeCodes.filter((code) => code !== 'en');
  for (const locale of locales) {
    const source = await readFile(
      `resources/js/localization/messages/territory/${locale}.ts`,
      'utf8',
    );
    assert.doesNotMatch(
      source,
      /from\s+['"]\.\/en['"]/,
      `${locale} must not alias the English Territory catalogue`,
    );
    const own = (await importDomainCatalogue('territory', locale)).default;
    assert.deepEqual(
      Object.keys(flattenMessages(own)).sort(),
      expected,
      `${locale} Territory keys drifted from the effective English contract`,
    );
  }
  console.log(
    `Territory localization: ${locales.length} complete catalogues match English, including reconciliation extensions.`,
  );
} finally {
  await server.close();
}
