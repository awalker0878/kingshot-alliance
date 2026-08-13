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
  { code: 'pt-BR', nativeName: 'Português (Brasil)', englishName: 'Portuguese (Brazil)', direction: 'ltr' },
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
  return locales.find((locale) => locale.code === code) ?? locales[0];
}

export function normalizeLocale(value: string | null | undefined): LocaleCode | null {
  if (!value) {
    return null;
  }

  const normalized = value.trim().replace('_', '-');

  if (isLocaleCode(normalized)) {
    return normalized;
  }

  const lower = normalized.toLowerCase();

  if (lower === 'pt' || lower.startsWith('pt-br')) {
    return 'pt-BR';
  }

  if (lower === 'zh' || lower.startsWith('zh-cn') || lower.startsWith('zh-hans')) {
    return 'zh-CN';
  }

  if (lower.startsWith('zh-tw') || lower.startsWith('zh-hant') || lower.startsWith('zh-hk')) {
    return 'zh-TW';
  }

  const language = lower.split('-')[0];
  return locales.find((locale) => locale.code.toLowerCase() === language)?.code ?? null;
}
