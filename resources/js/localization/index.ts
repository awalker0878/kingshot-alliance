import { computed, readonly, ref } from 'vue';

import { loadDomains, resolveMessage } from './loader';
import {
  defaultLocale,
  localeDefinition,
  locales,
  normalizeLocale,
  type LocaleCode,
} from './locales';
import { domainsForPage, hasLocaleCatalogue, type LocalizationDomain } from './registry';
import type { TranslationParams } from './types';

const storageKey = 'kingshot.locale';
const currentLocale = ref<LocaleCode>(defaultLocale);
let currentPageDomains: LocalizationDomain[] = [];
let localeRequest = 0;

function interpolate(value: string, params?: TranslationParams): string {
  if (!params) return value;

  return value.replace(/\{([A-Za-z0-9_]+)\}/g, (match, key: string) => {
    const replacement = params[key];
    return replacement === undefined ? match : String(replacement);
  });
}

function applyDocumentLocale(locale: LocaleCode): void {
  if (typeof document === 'undefined') return;
  const definition = localeDefinition(locale);
  document.documentElement.lang = definition.code;
  document.documentElement.dir = definition.direction;
}

function activeDomains(): LocalizationDomain[] {
  return ['core', ...currentPageDomains];
}

export async function ensureDomain(domain: LocalizationDomain): Promise<void> {
  await loadDomains(currentLocale.value, ['core', domain]);
}

export async function ensurePageDomains(pageName: string): Promise<void> {
  const domains = domainsForPage(pageName);
  await loadDomains(currentLocale.value, ['core', ...domains]);
  currentPageDomains = domains;
}

export async function setLocale(locale: LocaleCode, persist = true): Promise<void> {
  if (!hasLocaleCatalogue(locale)) return;

  const request = ++localeRequest;
  await loadDomains(locale, activeDomains());
  if (request !== localeRequest) return;

  currentLocale.value = locale;
  applyDocumentLocale(locale);

  if (persist && typeof window !== 'undefined') {
    window.localStorage.setItem(storageKey, locale);
  }
}

export async function initializeLocale(preferredLocale?: string | null): Promise<void> {
  const candidates: Array<string | null | undefined> = [preferredLocale];

  if (typeof window !== 'undefined') {
    candidates.push(window.localStorage.getItem(storageKey));
    candidates.push(...window.navigator.languages);
    candidates.push(window.navigator.language);
  }

  const locale = candidates
    .map((candidate) => normalizeLocale(candidate))
    .find(
      (candidate): candidate is LocaleCode => candidate !== null && hasLocaleCatalogue(candidate),
    );
  const selected = locale ?? defaultLocale;

  applyDocumentLocale(selected);
  await loadDomains(selected, ['core']);
  currentLocale.value = selected;
}

export function t(key: string, params?: TranslationParams): string {
  return interpolate(resolveMessage(currentLocale.value, activeDomains(), key) ?? key, params);
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
    locales.filter((locale) => hasLocaleCatalogue(locale.code)),
  );

  return {
    locale: readonly(currentLocale),
    definition,
    direction: computed(() => definition.value.direction),
    availableLocales,
    setLocale,
    ensureDomain,
    formatNumber,
    formatDate,
    formatRelativeTime,
    t,
  };
}

export { hasLocaleCatalogue } from './registry';
export type { LocalizationDomain } from './registry';
