import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createServer } from 'vite';
import { catalogueProblems, flattenMessages } from './lib/localization-completeness.mjs';

const read = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const unchanged = read('resources/data/localization/unchanged.json');
const server = await createServer({
  configFile: false,
  root: process.cwd(),
  server: { middlewareMode: true },
  appType: 'custom',
});
try {
  const { localizationDomains, importDomainCatalogue } = await server.ssrLoadModule(
    '/resources/js/localization/registry.ts',
  );
  const { localeCodes } = await server.ssrLoadModule('/resources/js/localization/locales.ts');
  const { loadCatalogue } = await server.ssrLoadModule('/resources/js/localization/loader.ts');
  const englishServer = read('lang/en.json');
  const failures = [];
  const phpFiles = (directory) =>
    fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
      const file = path.join(directory, entry.name);
      return entry.isDirectory() ? phpFiles(file) : file.endsWith('.php') ? [file] : [];
    });
  for (const file of phpFiles('app')) {
    const content = fs.readFileSync(file, 'utf8');
    for (const match of content.matchAll(
      /\b__\(\s*('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")\s*[,)]/g,
    )) {
      const source = match[1].slice(1, -1).replace(/\\(['"\\])/g, '$1');
      if (/\s|^[A-Z][a-z]+$/.test(source) && !(source in englishServer))
        failures.push({ file, source, reason: 'missing-server-source' });
    }
  }
  let entries = 0;
  for (const locale of localeCodes.filter((code) => code !== 'en')) {
    const allowed = [...unchanged.common, ...(unchanged.locales[locale] ?? [])];
    for (const domain of localizationDomains) {
      const english = flattenMessages(await loadCatalogue(domain, 'en'));
      const own = flattenMessages((await importDomainCatalogue(domain, locale)).default);
      const effective = flattenMessages(await loadCatalogue(domain, locale));
      failures.push(
        ...catalogueProblems(english, own, allowed).map((problem) => ({
          locale,
          domain,
          ...problem,
        })),
      );
      // An extension must not turn a translated value back into English at runtime.
      for (const key of Object.keys(english))
        if (effective[key] !== own[key])
          failures.push({ locale, domain, key, reason: 'runtime-overlay' });
      entries += Object.keys(english).length;
    }
    const file = path.join('lang', `${locale}.json`);
    failures.push(
      ...catalogueProblems(englishServer, fs.existsSync(file) ? read(file) : {}, allowed).map(
        (problem) => ({ locale, domain: 'server', ...problem }),
      ),
    );
  }
  if (failures.length) {
    console.error(JSON.stringify(failures, null, 2));
    process.exitCode = 1;
  } else
    console.log(
      `Localization completeness: ${entries} UI entries and ${Object.keys(englishServer).length} server messages per locale, all 16 translated locales. No English fallback or runtime overwrites.`,
    );
} finally {
  await server.close();
}
