export const localeCodes = [
  'en',
  'ar',
  'de',
  'es',
  'fr',
  'id',
  'it',
  'ja',
  'ko',
  'pl',
  'pt-BR',
  'ru',
  'th',
  'tr',
  'vi',
  'zh-CN',
  'zh-TW',
] as const;

export type LocaleCode = (typeof localeCodes)[number];
export type TextDirection = 'ltr' | 'rtl';

export type LocaleDefinition = {
  code: LocaleCode;
  nativeName: string;
  englishName: string;
  direction: TextDirection;
};

export const defaultLocale: LocaleCode = 'en';

export const locales: readonly LocaleDefinition[] = [
  { code: 'en', nativeName: 'English', englishName: 'English', direction: 'ltr' },
  { code: 'ar', nativeName: 'العربية', englishName: 'Arabic', direction: 'rtl' },
  { code: 'de', nativeName: 'Deutsch', englishName: 'German', direction: 'ltr' },
  { code: 'es', nativeName: 'Español', englishName: 'Spanish', direction: 'ltr' },
  { code: 'fr', nativeName: 'Français', englishName: 'French', direction: 'ltr' },
  { code: 'id', nativeName: 'Bahasa Indonesia', englishName: 'Indonesian', direction: 'ltr' },
  { code: 'it', nativeName: 'Italiano', englishName: 'Italian', direction: 'ltr' },
  { code: 'ja', nativeName: '日本語', englishName: 'Japanese', direction: 'ltr' },
  { code: 'ko', nativeName: '한국어', englishName: 'Korean', direction: 'ltr' },
  { code: 'pl', nativeName: 'Polski', englishName: 'Polish', direction: 'ltr' },
  {
    code: 'pt-BR',
    nativeName: 'Português (Brasil)',
    englishName: 'Portuguese (Brazil)',
    direction: 'ltr',
  },
  { code: 'ru', nativeName: 'Русский', englishName: 'Russian', direction: 'ltr' },
  { code: 'th', nativeName: 'ไทย', englishName: 'Thai', direction: 'ltr' },
  { code: 'tr', nativeName: 'Türkçe', englishName: 'Turkish', direction: 'ltr' },
  { code: 'vi', nativeName: 'Tiếng Việt', englishName: 'Vietnamese', direction: 'ltr' },
  { code: 'zh-CN', nativeName: '简体中文', englishName: 'Chinese (Simplified)', direction: 'ltr' },
  { code: 'zh-TW', nativeName: '繁體中文', englishName: 'Chinese (Traditional)', direction: 'ltr' },
] as const;

const localeSet = new Set<string>(localeCodes);

export function isLocaleCode(value: string): value is LocaleCode {
  return localeSet.has(value);
}

export function localeDefinition(code: LocaleCode): LocaleDefinition {
  return locales.find((locale) => locale.code === code) ?? locales[0]!;
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

  if (normalized === 'pt' || normalized === 'pt-br' || normalized.startsWith('pt-br-'))
    return 'pt-BR';

  if (
    normalized === 'zh-tw' ||
    normalized.startsWith('zh-tw-') ||
    normalized === 'zh-hant' ||
    normalized.startsWith('zh-hant-') ||
    normalized === 'zh-hk' ||
    normalized.startsWith('zh-hk-')
  )
    return 'zh-TW';

  if (
    normalized === 'zh' ||
    normalized === 'zh-cn' ||
    normalized.startsWith('zh-cn-') ||
    normalized === 'zh-hans' ||
    normalized.startsWith('zh-hans-')
  )
    return 'zh-CN';

  const language = normalized.split('-')[0];
  return localeCodes.find((code) => code === language) ?? null;
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
