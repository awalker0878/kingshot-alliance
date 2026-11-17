import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import prettier from 'prettier';
import { readLocaleRegistry } from './locale-registry.mjs';

const registry = readLocaleRegistry();
const codes = registry.locales.map(({ code }) => code);
const definitions = registry.locales.map(({ code, nativeName, englishName, direction }) => ({
  code,
  nativeName,
  englishName,
  direction,
}));
const aliases = registry.locales.flatMap(({ code, aliases }) =>
  aliases.map((alias) => ({ ...alias, code })),
);
const banner =
  'Generated from resources/data/localization/locales.json. Run npm run localization:generate; do not edit.';
const tsPath = 'resources/js/localization/locale-definitions.generated.ts';
const tsSource = `// ${banner}
export const localeCodes = ${JSON.stringify(codes)} as const;
export type LocaleCode = (typeof localeCodes)[number];
export type TextDirection = 'ltr' | 'rtl';
export type LocaleDefinition = {
  code: LocaleCode;
  nativeName: string;
  englishName: string;
  direction: TextDirection;
};
export const defaultLocale: LocaleCode = ${JSON.stringify(registry.defaultLocale)};
export const locales: readonly LocaleDefinition[] = ${JSON.stringify(definitions)} as const;
export const localeAliases: readonly { tag: string; descendants: boolean; code: LocaleCode }[] = ${JSON.stringify(aliases)} as const;
`;
const quote = (value) => "'" + value.replaceAll('\\', '\\\\').replaceAll("'", "\\'") + "'";
const phpSource = `<?php

declare(strict_types=1);

namespace App\\Shared\\Infrastructure\\Http;

// ${banner}
final class LocaleDefinitions
{
    public const DEFAULT = ${quote(registry.defaultLocale)};

    /** @var list<string> */
    public const CODES = [${codes.map(quote).join(', ')}];

    /** @var array<string, array{nativeName: string, englishName: string, direction: string}> */
    public const LOCALES = [
${definitions
  .map(
    ({ code, ...values }) =>
      `        ${quote(code)} => [${Object.entries(values)
        .map(([key, value]) => quote(key) + ' => ' + quote(value))
        .join(', ')}],`,
  )
  .join('\n')}
    ];

    /** @var list<array{tag: string, descendants: bool, code: string}> */
    public const ALIASES = [
${aliases.map((alias) => `        ['tag' => ${quote(alias.tag)}, 'descendants' => ${alias.descendants}, 'code' => ${quote(alias.code)}],`).join('\n')}
    ];
}
`;
const files = new Map([
  [
    tsPath,
    await prettier.format(tsSource, {
      ...(await prettier.resolveConfig(fileURLToPath(new URL('../../' + tsPath, import.meta.url)))),
      plugins: [],
      filepath: tsPath,
    }),
  ],
  ['app/Shared/Infrastructure/Http/LocaleDefinitions.php', phpSource],
]);
let stale = false;
for (const [file, content] of files) {
  if (process.argv.includes('--check')) {
    let actual;
    try {
      actual = readFileSync(file, 'utf8');
    } catch {
      /* Missing outputs are stale. */
    }
    if (actual !== content) {
      console.error(
        `Stale generated locale definitions: ${file}. Run npm run localization:generate.`,
      );
      stale = true;
    }
  } else writeFileSync(file, content);
}
if (stale) process.exitCode = 1;
else
  console.log(
    `Locale definitions ${process.argv.includes('--check') ? 'verified' : 'generated'}: ${codes.length} locales, ${aliases.length} aliases.`,
  );
