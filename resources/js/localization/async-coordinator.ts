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
  let pendingPage: { request: number; name: string; domains: Domain[]; cleanup(): void } | null =
    null;
  let localeRequest = 0;
  let pageRequest = 0;

  async function setLocale(next: Locale, persist = true): Promise<void> {
    const request = ++localeRequest;
    try {
      // Navigation may start during an in-flight language import.
      // The final commit must cover the newest page-domain selection.
      while (true) {
        const pageVersion = pageRequest;
        // The previous page remains visible until Inertia accepts the next one.
        const domains = [
          ...new Set([options.coreDomain, ...currentPageDomains, ...(pendingPage?.domains ?? [])]),
        ];
        try {
          await options.load(next, domains);
        } catch (error) {
          if (request !== localeRequest) return;
          if (pageVersion !== pageRequest) continue;
          throw error;
        }
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

  function preparePageDomains(pageName: string, signal?: AbortSignal) {
    const request = ++pageRequest;
    const domains = options.domainsForPage(pageName);

    let cancelled = false;
    const cleanup = () => signal?.removeEventListener('abort', cancel);
    const cancel = () => {
      cleanup();
      cancelled = true;
      if (pendingPage?.request === request) {
        pendingPage = null;
        ++pageRequest;
      }
    };
    pendingPage?.cleanup();
    pendingPage = { request, name: pageName, domains, cleanup };
    signal?.addEventListener('abort', cancel, { once: true });
    if (signal?.aborted) cancel();

    const ready = (async () => {
      try {
        while (true) {
          if (cancelled) throw new Error('Page localization cancelled');
          const requestedLocale = locale;
          await options.load(requestedLocale, [options.coreDomain, ...domains]);
          if (cancelled) throw new Error('Page localization cancelled');
          if (request !== pendingPage?.request || requestedLocale === locale) return;
        }
      } catch (error) {
        cancel();
        throw error;
      }
    })();

    return {
      ready,
      cancel,
      accept() {
        if (!cancelled && pendingPage?.request === request) activatePageDomains(pageName);
      },
    };
  }

  // Called synchronously when Inertia's Vue adapter accepts a page, including
  // replace/history visits (which do not all emit a navigate event).
  function activatePageDomains(pageName: string): void {
    currentPageDomains = options.domainsForPage(pageName);
    if (pendingPage?.name === pageName) {
      pendingPage.cleanup();
      pendingPage = null;
    }
    ++pageRequest;
  }

  async function ensurePageDomains(pageName: string): Promise<void> {
    const page = preparePageDomains(pageName);
    await page.ready;
    page.accept();
  }

  async function ensureDomain(domain: Domain): Promise<void> {
    await options.load(locale, [options.coreDomain, domain]);
  }

  return {
    setLocale,
    ensurePageDomains,
    preparePageDomains,
    activatePageDomains,
    ensureDomain,
    getLocale: () => locale,
    activeDomains: () => [options.coreDomain, ...currentPageDomains],
  };
}
