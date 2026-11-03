export interface LocaleLoadCoordinatorOptions<Locale extends string, Domain extends string> {
  initialLocale: Locale;
  coreDomain: Domain;
  domainsForPage(pageName: string): Domain[];
  load(locale: Locale, domains: Domain[]): Promise<void>;
  /** Commit reactive UI state only after the latest successful load. */
  commit(locale: Locale): void;
  /** Replaceable persistence boundary for Phase 5B; generation logic stays untouched. */
  persistSelection?(locale: Locale, persist: boolean): void;
  loadFailed?(error: unknown): void;
}

/**
 * Single generation-guarded commit path shared by locale and page navigation.
 * Does not own cookie/localStorage precedence (reserved for PR 5B).
 */
export function createLocaleLoadCoordinator<Locale extends string, Domain extends string>(
  options: LocaleLoadCoordinatorOptions<Locale, Domain>,
) {
  let locale = options.initialLocale;
  let currentPageDomains: Domain[] = [];
  let requestedPageDomains: Domain[] = [];
  let localeRequest = 0;
  let pageRequest = 0;

  async function setLocale(next: Locale, persist = true): Promise<void> {
    const request = ++localeRequest;
    try {
      // Navigation may start during an in-flight language import.
      // The final commit must cover the newest page-domain selection.
      while (true) {
        const pageVersion = pageRequest;
        const domains = pageVersion ? requestedPageDomains : currentPageDomains;
        await options.load(next, [options.coreDomain, ...domains]);
        if (request !== localeRequest) return;
        if (pageVersion === pageRequest) break;
      }
    } catch (error) {
      if (request === localeRequest) options.loadFailed?.(error);
      throw error;
    }
    if (request !== localeRequest) return;
    locale = next;
    options.commit(next);
    options.persistSelection?.(next, persist);
  }

  async function ensurePageDomains(pageName: string): Promise<void> {
    const request = ++pageRequest;
    const domains = options.domainsForPage(pageName);
    requestedPageDomains = domains;
    while (true) {
      const requestedLocale = locale;
      await options.load(requestedLocale, [options.coreDomain, ...domains]);
      if (request !== pageRequest) return;
      if (requestedLocale === locale) {
        currentPageDomains = domains;
        return;
      }
    }
  }

  async function ensureDomain(domain: Domain): Promise<void> {
    await options.load(locale, [options.coreDomain, domain]);
  }

  return {
    setLocale,
    ensurePageDomains,
    ensureDomain,
    getLocale: () => locale,
    activeDomains: () => [options.coreDomain, ...currentPageDomains],
  };
}
