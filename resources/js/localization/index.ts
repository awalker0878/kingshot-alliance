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
import { createLocalePreferences, preferenceStorage } from './preferences';
import type { TranslationParams } from './types';

const currentLocale = ref<LocaleCode>(defaultLocale);
const localeLoadFailed = ref(false);
const desiredLocale = ref<LocaleCode>(defaultLocale);
const serverDocumentLocale = typeof document === 'undefined' ? null : document.documentElement.lang;
let preferredStartupLocale: string | null | undefined;
let listenersInstalled = false;

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
  loadFailed() {
    localeLoadFailed.value = true;
  },
});

const preferences = createLocalePreferences({
  readCookie(name) {
    if (typeof document === 'undefined') return null;
    const prefix = `${name}=`;
    return (
      document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(prefix))
        ?.slice(prefix.length) ?? null
    );
  },
  writeCookie(name, value) {
    if (typeof document === 'undefined' || typeof window === 'undefined') return;
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${value}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
  },
  writeStorage(key, value) {
    if (typeof window !== 'undefined') window.localStorage.setItem(key, value);
  },
  fallbackLocale() {
    const candidates = [preferredStartupLocale];
    candidates.push(serverDocumentLocale);
    if (typeof window !== 'undefined')
      candidates.push(...window.navigator.languages, window.navigator.language);
    return candidates.map(normalizeLocale).find((locale) => locale !== null) ?? defaultLocale;
  },
  newId: () => globalThis.crypto.randomUUID().replaceAll('-', ''),
  load: (locale, authorize) => coordinator.setLocale(locale, false, authorize),
  desired(locale) {
    desiredLocale.value = locale;
  },
});

function installPreferenceListeners(): void {
  if (listenersInstalled || typeof window === 'undefined') return;
  listenersInstalled = true;
  const reconcile = (value?: string | null) => {
    void preferences.synchronize(value).catch(() => {
      /* Existing UI and retry remain available. */
    });
  };
  window.addEventListener('storage', (event) => {
    if (event.key === preferenceStorage || event.key === null) reconcile(event.newValue);
  });
  window.addEventListener('focus', () => reconcile());
  window.addEventListener('pageshow', () => reconcile());
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') reconcile();
  });
}

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
  if (persist) await preferences.select(locale);
  else await coordinator.setLocale(locale, false);
}

export async function initializeLocale(preferredLocale?: string | null): Promise<void> {
  preferredStartupLocale = preferredLocale;
  installPreferenceListeners();
  try {
    await preferences.initialize();
  } catch {
    // Mount a usable shell if the selected catalogue fails during startup.
    // Keep the shared preference and desired locale intact for explicit retry.
    await coordinator.setLocale(defaultLocale, false);
    localeLoadFailed.value = true;
  }
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
    desiredLocale: readonly(desiredLocale),
    definition,
    direction: computed(() => definition.value.direction),
    availableLocales,
    setLocale,
    retryLocale: preferences.retry,
    ensureDomain,
    formatNumber,
    formatDate,
    formatRelativeTime,
    t,
  };
}

export { hasLocaleCatalogue } from './registry';
export type { LocalizationDomain } from './registry';
