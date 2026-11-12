import {
  defaultLocale,
  localeAliases,
  localeCodes,
  locales,
  type LocaleCode,
  type LocaleDefinition,
} from './locale-definitions.generated.ts';

export { defaultLocale, localeCodes, locales } from './locale-definitions.generated.ts';
export type {
  LocaleCode,
  LocaleDefinition,
  TextDirection,
} from './locale-definitions.generated.ts';

const localeSet = new Set<string>(localeCodes);

export function isLocaleCode(value: string): value is LocaleCode {
  return localeSet.has(value);
}

export function localeDefinition(code: LocaleCode): LocaleDefinition {
  return (
    locales.find((locale) => locale.code === code) ??
    locales.find((locale) => locale.code === defaultLocale)!
  );
}

// Permit standard language-region/script subtags, but reject malformed/untrusted paths
// before falling back to the language portion.
const localeTagPattern = /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*(?:-[a-z0-9](?:-[a-z0-9]{2,8})+)*$/;

export function normalizeLocale(value: string | null | undefined): LocaleCode | null {
  if (typeof value !== 'string' || value.includes('\0')) return null;
  // Match PHP's standalone trim after rejecting NUL. HTTP has its own SP/HTAB grammar.
  const normalized = value
    .replace(/^[ \t\n\r\v]+|[ \t\n\r\v]+$/g, '')
    .replaceAll('_', '-')
    .toLowerCase();
  if (normalized.length > 128 || !localeTagPattern.test(normalized)) return null;

  return (
    localeAliases.find(
      ({ tag, descendants }) =>
        normalized === tag || (descendants && normalized.startsWith(tag + '-')),
    )?.code ?? null
  );
}

/** Explicit preference precedence comes from caller-provided source order. */
export function selectFirstSupportedLocale(
  candidates: readonly (string | null | undefined)[],
): LocaleCode {
  for (const candidate of candidates) {
    const locale = normalizeLocale(candidate);
    if (locale !== null) return locale;
  }
  return defaultLocale;
}
