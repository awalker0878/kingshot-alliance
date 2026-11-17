import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createServer } from 'vite';
import { pageTranslationContracts, placeholders } from './lib/localization-contracts.mjs';

const root = process.cwd();
function pages(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? pages(target) : target.endsWith('.vue') ? [target] : [];
  });
}

// Vite resolves the production import.meta.glob registry and all real loader overlays.
const server = await createServer({
  configFile: false,
  root,
  server: { middlewareMode: true },
  appType: 'custom',
});
try {
  const { domainsForPage } = await server.ssrLoadModule('/resources/js/localization/registry.ts');
  const { loadDomains, resolveMessage } = await server.ssrLoadModule(
    '/resources/js/localization/loader.ts',
  );
  const { locales } = await server.ssrLoadModule('/resources/js/localization/locales.ts');
  const failures = new Set();
  const entries = pages(path.join(root, 'resources/js/pages'));
  let callCount = 0;
  let dynamicCalls = 0;
  for (const page of entries) {
    const name = path
      .relative(path.join(root, 'resources/js/pages'), page)
      .replace(/\.vue$/, '')
      .split(path.sep)
      .join('/');
    const domains = ['core', ...domainsForPage(name)];
    const contract = pageTranslationContracts(page, root);
    callCount += contract.calls.length;
    dynamicCalls += contract.dynamicCalls;
    for (const locale of locales) await loadDomains(locale.code, domains);
    for (const { key, parameterNames, file } of contract.calls) {
      const english = resolveMessage('en', domains, key);
      if (english === null) {
        failures.add(`${name}: unresolved ${key} (${file})`);
        continue;
      }
      const expected = placeholders(english);
      if (parameterNames !== null) {
        for (const parameter of expected) {
          if (!parameterNames.includes(parameter))
            failures.add(`${name}: ${key} needs {${parameter}} (${file})`);
        }
      }
      for (const locale of locales) {
        const localized = resolveMessage(locale.code, domains, key);
        if (
          localized === null ||
          JSON.stringify(placeholders(localized)) !== JSON.stringify(expected)
        ) {
          failures.add(`${locale.code}: placeholder mismatch for ${key} (${name})`);
        }
      }
    }
  }
  if (failures.size) {
    console.error([...failures].join('\n'));
    process.exitCode = 1;
  } else {
    console.log(
      `Localization contracts: ${entries.length} pages, ${callCount} literal calls including dependencies, ${locales.length} locales. Dynamic calls (${dynamicCalls}, including shared dependencies) require their domain-specific tests.`,
    );
  }
} finally {
  await server.close();
}
