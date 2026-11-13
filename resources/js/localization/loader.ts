import { defaultLocale, type LocaleCode } from './locales';
import { importDomainCatalogue, type LocalizationDomain } from './registry';
import type { MessageCatalogue } from './types';

const catalogues = new Map<string, MessageCatalogue>();
const pending = new Map<string, Promise<MessageCatalogue>>();

function cacheKey(domain: LocalizationDomain, locale: LocaleCode): string {
  return `${domain}:${locale}`;
}
function readPath(source: MessageCatalogue | undefined, path: string): string | null {
  if (!source) return null;
  const value = path.split('.').reduce<unknown>((node, segment) => {
    if (!node || typeof node !== 'object') return null;
    return (node as Record<string, unknown>)[segment] ?? null;
  }, source);
  return typeof value === 'string' ? value : null;
}
export async function loadCatalogue(
  domain: LocalizationDomain,
  locale: LocaleCode,
): Promise<MessageCatalogue> {
  const key = cacheKey(domain, locale);
  const cached = catalogues.get(key);
  if (cached) return cached;
  const existing = pending.get(key);
  if (existing) return existing;
  const request = importDomainCatalogue(domain, locale)
    .then((module) => module.default)
    .then((catalogue) => {
      catalogues.set(key, catalogue);
      return catalogue;
    })
    .finally(() => {
      // Clear pending on both success and failure; a rejected import must be retryable.
      pending.delete(key);
    });
  pending.set(key, request);
  return request;
}
export async function loadDomain(locale: LocaleCode, domain: LocalizationDomain): Promise<void> {
  await loadCatalogue(domain, defaultLocale);
  if (locale !== defaultLocale) await loadCatalogue(domain, locale);
}
export async function loadDomains(
  locale: LocaleCode,
  domains: readonly LocalizationDomain[],
): Promise<void> {
  await Promise.all([...new Set(domains)].map((domain) => loadDomain(locale, domain)));
}
export function isDomainLoaded(locale: LocaleCode, domain: LocalizationDomain): boolean {
  return (
    catalogues.has(cacheKey(domain, defaultLocale)) &&
    (locale === defaultLocale || catalogues.has(cacheKey(domain, locale)))
  );
}
export function resolveMessage(
  locale: LocaleCode,
  domains: readonly LocalizationDomain[],
  path: string,
): string | null {
  const ordered = [...new Set(domains)];
  if (locale !== defaultLocale) {
    for (const domain of ordered) {
      const localized = readPath(catalogues.get(cacheKey(domain, locale)), path);
      if (localized !== null) return localized;
    }
  }
  for (const domain of ordered) {
    const fallback = readPath(catalogues.get(cacheKey(domain, defaultLocale)), path);
    if (fallback !== null) return fallback;
  }
  return null;
}
