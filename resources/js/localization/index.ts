import { computed, readonly, ref } from 'vue';

import { createLocaleLoadCoordinator } from './async-coordinator';
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
const localeLoadFailed = ref(false);

function interpolate(value: string, params?: TranslationParams): string {
  if (!params) return value;

  return value.replace(/\{([A-Za-z0-9_]+)\}/g, (match, key: string) => {
    const replacement = params[key];
    return replacement === undefined ? match : String(replacement);
  });
}

function applyDocumentLanguage(locale: LocaleCode): void {
  if (typeof document === 'undefined') return;
  const definition = localeDefinition(locale);
  document.documentElement.lang = definition.code;
  document.documentElement.dir = definition.direction;
}

function writeLocaleCookie(locale: LocaleCode): void {
  if (typeof document === 'undefined' || typeof window === 'undefined') return;
  // Keep server validation and account-email requests in the selected UI language.
  const secure = window.location.protocol === 'https:' ? '; Secure' : '';
  document.cookie = `kingshot_locale=${locale}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
}

const coordinator = createLocaleLoadCoordinator<LocaleCode, LocalizationDomain>({
  initialLocale: defaultLocale,
  coreDomain: 'core',
  domainsForPage,
  load: loadDomains,
  commit(locale) {
    currentLocale.value = locale;
    localeLoadFailed.value = false;
    applyDocumentLanguage(locale);
  },
  persistSelection(locale, persist) {
    // PR 5B can replace cookie and local-storage reconciliation here without
    // modifying the generation-guarded request/commit lifecycle.
    writeLocaleCookie(locale);

    if (persist && typeof window !== 'undefined') {
      window.localStorage.setItem(storageKey, locale);
    }
  },
  loadFailed() {
    localeLoadFailed.value = true;
  },
});

export async function ensureDomain(domain: LocalizationDomain): Promise<void> {
  await coordinator.ensureDomain(domain);
}

export async function ensurePageDomains(pageName: string): Promise<void> {
  await coordinator.ensurePageDomains(pageName);
}

export function preparePageDomains(pageName: string, signal?: AbortSignal) {
  return coordinator.preparePageDomains(pageName, signal);
}

export function activatePageDomains(pageName: string): void {
  coordinator.activatePageDomains(pageName);
}

export async function setLocale(locale: LocaleCode, persist = true): Promise<void> {
  if (!hasLocaleCatalogue(locale)) return;
  await coordinator.setLocale(locale, persist);
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

  await coordinator.setLocale(selected, false);
}

export function t(key: string, params?: TranslationParams): string {
  return interpolate(
    resolveMessage(currentLocale.value, coordinator.activeDomains(), key) ?? key,
    params,
  );
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
    localeLoadFailed: readonly(localeLoadFailed),
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
