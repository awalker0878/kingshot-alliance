import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import {
  catalogueProblems,
  expandMessages,
  preservedTranslation,
} from '../lib/localization-completeness.mjs';

const baseline = path.resolve(process.argv[2] ?? '.translation-baseline-input');
const drafts = path.resolve(process.argv[3] ?? '.translation-output');
const read = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const input = read(path.join(baseline, 'input.json'));
if (!input.baselineRef)
  throw new Error('Export an immutable baseline Git revision before compiling.');
const effective = read(path.join(baseline, 'effective.json'));
const base = read(path.join(baseline, 'base.json'));
const reviewed = read('resources/data/localization/reviewed.json');
const keyCorrections = read('resources/data/localization/reviewed-keys.json');
const unchanged = read('resources/data/localization/unchanged.json');
const locales = process.argv[4] ? [process.argv[4]] : input.locales;
const files = new Map(),
  failures = [],
  counts = {};
const accountSource = fs.readFileSync('lang/en/accounts.php', 'utf8');
const phpLiteral = /=>\s*('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")\s*,/g;
const decodePhp = (value) => value.slice(1, -1).replace(/\\(['"\\])/g, '$1');
const encodePhp = (value) => `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;

for (const locale of locales) {
  if (!input.locales.includes(locale)) throw new Error(`Unsupported locale ${locale}`);
  const sourceFile = path.join(drafts, `${locale}.jsonl`);
  if (!fs.existsSync(sourceFile)) {
    failures.push({ locale, reason: 'missing-draft' });
    continue;
  }
  const records = new Map(
    fs
      .readFileSync(sourceFile, 'utf8')
      .trim()
      .split('\n')
      .map((line) => {
        const record = JSON.parse(line);
        return [record.en, record];
      }),
  );
  const allowed = [...unchanged.common, ...(unchanged.locales[locale] ?? [])];
  const translate = (source) => reviewed[source]?.[locale] ?? records.get(source)?.translation;
  counts[locale] = {
    preserved: 0,
    recoveredFromBase: 0,
    filled: 0,
    server: input.backend.length,
    accounts: input.accounts.length,
  };
  for (const [domain, languages] of Object.entries(effective)) {
    const localized = { ...base[domain][locale], ...languages[locale] };
    for (const [key, source] of Object.entries(languages.en)) {
      const previous = preservedTranslation(
        source,
        languages[locale][key],
        base[domain].en[key],
        base[domain][locale][key],
      );
      if (previous) {
        counts[locale].preserved++;
        if (languages[locale][key] === source) counts[locale].recoveredFromBase++;
      } else counts[locale].filled++;
      localized[key] =
        keyCorrections[`${domain}:${key}`]?.[locale] ?? previous ?? translate(source);
    }
    failures.push(
      ...catalogueProblems(languages.en, localized, allowed).map((problem) => ({
        locale,
        domain,
        ...problem,
      })),
    );
    files.set(
      `resources/js/localization/messages/${domain}/${locale}.ts`,
      `import type { MessageCatalogue } from '../../types';\n\nconst messages = ${JSON.stringify(expandMessages(localized), null, 2)} satisfies MessageCatalogue;\n\nexport default messages;\n`,
    );
  }
  const english = Object.fromEntries(input.backend.map((source) => [source, source]));
  const localized = Object.fromEntries(input.backend.map((source) => [source, translate(source)]));
  failures.push(
    ...catalogueProblems(english, localized, allowed).map((problem) => ({
      locale,
      domain: 'server',
      ...problem,
    })),
  );
  files.set(`lang/${locale}.json`, JSON.stringify(localized, null, 2) + '\n');
  const accountEnglish = Object.fromEntries(input.accounts.map((source) => [source, source]));
  const accountLocalized = Object.fromEntries(
    input.accounts.map((source) => [source, translate(source)]),
  );
  failures.push(
    ...catalogueProblems(accountEnglish, accountLocalized, allowed).map((problem) => ({
      locale,
      domain: 'accounts',
      ...problem,
    })),
  );
  // Invalid drafts never reach disk, so the temporary empty value is never used at runtime.
  const accounts = accountSource.replace(phpLiteral, (match, literal) =>
    match.replace(literal, encodePhp(translate(decodePhp(literal)) ?? '')),
  );
  files.set(`lang/${locale}/accounts.php`, accounts);
}
fs.writeFileSync(
  path.join(drafts, 'compile-report.json'),
  JSON.stringify({ baselineRef: input.baselineRef, counts, failures }, null, 2) + '\n',
);
if (failures.length) {
  console.error(
    `${failures.length} translation issues; inspect ${path.join(drafts, 'compile-report.json')}. No application files changed.`,
  );
  process.exitCode = 1;
} else {
  files.set(
    'lang/en.json',
    JSON.stringify(Object.fromEntries(input.backend.map((source) => [source, source])), null, 2) +
      '\n',
  );
  for (const [file, content] of files) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content);
  }
  console.log(JSON.stringify({ files: files.size, counts }));
}
