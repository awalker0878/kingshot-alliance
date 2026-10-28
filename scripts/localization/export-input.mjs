import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createServer } from 'vite';

const root = process.cwd();
const output = path.resolve(process.argv[2] ?? '.translation-work/input');
fs.mkdirSync(output, { recursive: true });
const flatten = (node, prefix = '', result = {}) => {
  for (const [key, value] of Object.entries(node)) {
    const name = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'string') result[name] = value;
    else flatten(value, name, result);
  }
  return result;
};
const decodePhp = (value) => value.slice(1, -1).replace(/\\(['"\\])/g, '$1');
const phpLiteral = '(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")';
const sources = new Map();
function add(text, key) {
  if (!sources.has(text)) sources.set(text, { en: text, keys: [] });
  if (!sources.get(text).keys.includes(key)) sources.get(text).keys.push(key);
}
function files(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const file = path.join(directory, entry.name);
    return entry.isDirectory() ? files(file) : file.endsWith('.php') ? [file] : [];
  });
}
const server = await createServer({
  configFile: false,
  root,
  server: { middlewareMode: true },
  appType: 'custom',
});
try {
  const { localizationDomains: domains } = await server.ssrLoadModule(
    '/resources/js/localization/registry.ts',
  );
  const { localeCodes } = await server.ssrLoadModule('/resources/js/localization/locales.ts');
  const { loadCatalogue } = await server.ssrLoadModule('/resources/js/localization/loader.ts');
  const locales = localeCodes.filter((locale) => locale !== 'en');
  const effective = {},
    memory = Object.fromEntries(locales.map((locale) => [locale, {}]));
  for (const domain of domains) {
    effective[domain] = {};
    for (const locale of localeCodes)
      effective[domain][locale] = flatten(await loadCatalogue(domain, locale));
    for (const locale of locales) {
      for (const [key, english] of Object.entries(effective[domain].en)) {
        const local = effective[domain][locale][key];
        if (!local || local === english) add(english, `${domain}:${key}`);
        else {
          memory[locale][english] ??= [];
          if (!memory[locale][english].includes(local)) memory[locale][english].push(local);
        }
      }
    }
  }
  const backend = new Set();
  for (const file of files(path.join(root, 'app'))) {
    const source = fs.readFileSync(file, 'utf8');
    for (const match of source.matchAll(new RegExp(`\\b__\\(\\s*(${phpLiteral})\\s*[,)]`, 'g'))) {
      const english = decodePhp(match[1]);
      // Dotted catalogue keys are resolved by Laravel; these are English-key messages.
      if (!/\s|^[A-Z][a-z]+$/.test(english)) continue;
      backend.add(english);
      add(english, `server:${path.relative(root, file)}`);
    }
  }
  const accountsSource = fs.readFileSync(path.join(root, 'lang/en/accounts.php'), 'utf8');
  const accounts = [...accountsSource.matchAll(new RegExp(`=>\\s*(${phpLiteral})\\s*,`, 'g'))].map(
    (match) => decodePhp(match[1]),
  );
  for (const english of accounts) add(english, 'server:accounts');
  const input = {
    version: 1,
    locales,
    items: [...sources.values()],
    memory,
    backend: [...backend].sort(),
    accounts: [...new Set(accounts)],
  };
  fs.writeFileSync(path.join(output, 'input.json'), JSON.stringify(input, null, 2));
  fs.writeFileSync(path.join(output, 'effective.json'), JSON.stringify(effective, null, 2));
  console.log(
    JSON.stringify({
      uniqueSources: sources.size,
      serverMessages: backend.size,
      accountMessages: accounts.length,
      locales: locales.length,
    }),
  );
} finally {
  await server.close();
}
