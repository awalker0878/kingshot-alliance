import { computed, readonly, ref } from 'vue';

import {
  defaultLocale,
  localeDefinition,
  locales,
  normalizeLocale,
  type LocaleCode,
} from './locales';
import { hasMessageCatalogue, messages } from './messages';
import { publicMessages } from './messages/public';

const storageKey = 'kingshot.locale';
const currentLocale = ref<LocaleCode>(defaultLocale);

type TranslationParams = Record<string, string | number>;

function catalogueFor(locale: LocaleCode) {
  return messages[locale] ?? messages[defaultLocale];
}

function publicCatalogueFor(locale: LocaleCode) {
  return publicMessages[locale] ?? publicMessages[defaultLocale];
}

function readPath(source: unknown, path: string): string | null {
  const value = path.split('.').reduce<unknown>((node, segment) => {
    if (!node || typeof node !== 'object') {
      return null;
    }

    return (node as Record<string, unknown>)[segment] ?? null;
  }, source);

  return typeof value === 'string' ? value : null;
}

function interpolate(value: string, params?: TranslationParams): string {
  if (!params) {
    return value;
  }

  return value.replace(/\{([A-Za-z0-9_]+)\}/g, (match, key: string) => {
    const replacement = params[key];
    return replacement === undefined ? match : String(replacement);
  });
}

function applyDocumentLocale(locale: LocaleCode): void {
  if (typeof document === 'undefined') {
    return;
  }

  const definition = localeDefinition(locale);
  document.documentElement.lang = definition.code;
  document.documentElement.dir = definition.direction;
}

export function setLocale(locale: LocaleCode, persist = true): void {
  if (!hasMessageCatalogue(locale)) {
    return;
  }

  currentLocale.value = locale;
  applyDocumentLocale(locale);

  if (persist && typeof window !== 'undefined') {
    window.localStorage.setItem(storageKey, locale);
  }
}

export function initializeLocale(preferredLocale?: string | null): void {
  const candidates: Array<string | null | undefined> = [preferredLocale];

  if (typeof window !== 'undefined') {
    candidates.push(window.localStorage.getItem(storageKey));
    candidates.push(...window.navigator.languages);
    candidates.push(window.navigator.language);
  }

  const locale = candidates
    .map((candidate) => normalizeLocale(candidate))
    .find(
      (candidate): candidate is LocaleCode => candidate !== null && hasMessageCatalogue(candidate),
    );

  setLocale(locale ?? defaultLocale, false);
}

export function t(key: string, params?: TranslationParams): string {
  const localized =
    readPath(catalogueFor(currentLocale.value), key) ??
    readPath(publicCatalogueFor(currentLocale.value), key);
  const fallback =
    readPath(catalogueFor(defaultLocale), key) ?? readPath(publicCatalogueFor(defaultLocale), key);

  return interpolate(localized ?? fallback ?? key, params);
}

export function formatNumber(value: number, options?: Intl.NumberFormatOptions): string {
  return new Intl.NumberFormat(currentLocale.value, options).format(value);
}

export function formatDate(
  value: Date | string | number,
  options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },
): string {
  return new Intl.DateTimeFormat(currentLocale.value, options).format(new Date(value));
}

export function formatRelativeTime(value: number, unit: Intl.RelativeTimeFormatUnit): string {
  return new Intl.RelativeTimeFormat(currentLocale.value, { numeric: 'auto' }).format(value, unit);
}

export function useLocale() {
  const definition = computed(() => localeDefinition(currentLocale.value));
  const availableLocales = computed(() =>
    locales.filter((locale) => hasMessageCatalogue(locale.code)),
  );

  return {
    locale: readonly(currentLocale),
    definition,
    direction: computed(() => definition.value.direction),
    availableLocales,
    setLocale,
    t,
    formatNumber,
    formatDate,
    formatRelativeTime,
  };
}
