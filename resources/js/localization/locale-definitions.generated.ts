// Generated from resources/data/localization/locales.json. Run npm run localization:generate; do not edit.
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
export const localeAliases: readonly { tag: string; descendants: boolean; code: LocaleCode }[] = [
  { tag: 'en', descendants: true, code: 'en' },
  { tag: 'ar', descendants: true, code: 'ar' },
  { tag: 'de', descendants: true, code: 'de' },
  { tag: 'es', descendants: true, code: 'es' },
  { tag: 'fr', descendants: true, code: 'fr' },
  { tag: 'id', descendants: true, code: 'id' },
  { tag: 'it', descendants: true, code: 'it' },
  { tag: 'ja', descendants: true, code: 'ja' },
  { tag: 'ko', descendants: true, code: 'ko' },
  { tag: 'pl', descendants: true, code: 'pl' },
  { tag: 'pt', descendants: false, code: 'pt-BR' },
  { tag: 'pt-br', descendants: true, code: 'pt-BR' },
  { tag: 'ru', descendants: true, code: 'ru' },
  { tag: 'th', descendants: true, code: 'th' },
  { tag: 'tr', descendants: true, code: 'tr' },
  { tag: 'vi', descendants: true, code: 'vi' },
  { tag: 'zh', descendants: false, code: 'zh-CN' },
  { tag: 'zh-cn', descendants: true, code: 'zh-CN' },
  { tag: 'zh-hans', descendants: true, code: 'zh-CN' },
  { tag: 'zh-tw', descendants: true, code: 'zh-TW' },
  { tag: 'zh-hant', descendants: true, code: 'zh-TW' },
  { tag: 'zh-hk', descendants: true, code: 'zh-TW' },
] as const;
