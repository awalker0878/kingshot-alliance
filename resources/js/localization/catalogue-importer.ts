type Manifest = Record<string, { file: string }>;

export function createCatalogueImporter<T>(
  modules: Record<string, () => Promise<T>>,
  browser: {
    enabled: () => boolean;
    baseUrl: () => string;
    currentModuleUrl: () => string;
    manifest: () => Promise<Manifest>;
    importUrl: (url: string) => Promise<T>;
  },
): (path: string) => Promise<T> {
  const failures = new Map<string, number>();
  let manifest: Promise<Manifest> | undefined;

  async function retry(path: string, attempt: number): Promise<T> {
    // A failed metadata request must remain retryable too. Only successful,
    // build-matched manifests are retained; never import a newer release's code.
    manifest ??= browser
      .manifest()
      .then((value) => {
        const current = new URL(browser.currentModuleUrl());
        current.search = '';
        current.hash = '';
        if (
          !Object.values(value).some(
            (entry) =>
              entry &&
              typeof entry.file === 'string' &&
              new URL(entry.file, browser.baseUrl()).href === current.href,
          )
        ) {
          throw new Error('The application build changed; reload before changing language.');
        }
        return value;
      })
      .catch((error: unknown) => {
        manifest = undefined;
        throw error;
      });
    const entries = await manifest;
    const file = entries[`resources/js/localization/${path.slice(2)}`]?.file;
    if (typeof file !== 'string' || !/^assets\/[A-Za-z0-9_-]+\.js$/.test(file)) {
      throw new Error(`Invalid localization chunk for ${path}`);
    }
    const url = new URL(file, browser.baseUrl());
    // Native module maps retain a failed import by URL. A new request identity
    // is needed after each failed attempt, independently of our promise cache.
    url.searchParams.set('locale-retry', String(attempt));
    return browser.importUrl(url.href);
  }

  return async (path) => {
    const importer = modules[path];
    if (!importer) throw new Error(`Missing localization catalogue: ${path}`);
    const attempt = failures.get(path) ?? 0;
    try {
      return attempt > 0 && browser.enabled() ? await retry(path, attempt) : await importer();
    } catch (error) {
      failures.set(path, attempt + 1);
      throw error;
    }
  };
}
