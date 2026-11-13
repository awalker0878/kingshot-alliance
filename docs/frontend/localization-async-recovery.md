# Localization load recovery

Page-domain loading keeps the accepted page separate from pending navigation. A failed or cancelled visit cannot change the domains needed by the page still displayed. Locale commits remain generation-guarded and load both accepted and pending domains before changing document language, direction or preference storage.

There are two independent failure caches. The application's pending-promise map must discard rejected imports, and a browser's native module map can retain the rejected module URL. Retrying the same `import()` alone therefore does not recover a failed production chunk.

The registry keeps ordinary lazy imports for first attempts. After failure, an explicit retry reads the deployed Vite manifest and checks that it still contains the currently running JavaScript module. It resolves only the requested canonical catalogue to an `assets/*.js` file and imports it with an increasing `locale-retry` query parameter. Successful metadata is shared; failed metadata requests are discarded. A manifest that no longer contains the running module fails closed and requires a page reload. No new dependencies, duplicate catalogues, automatic reloads or changes to translation values are involved.

The existing runtime image includes `public/build/manifest.json`; nginx serves it as a static file. That additional request happens only during recovery. Each explicit retry retains the selected language and active page until all required translations load successfully.

Validation includes the original source regressions, failed/cancelled navigation tests, seven importer tests, and production browser scenarios on desktop and mobile. The browser failure tests disable service workers so Playwright can intercept actual module requests; production service-worker behaviour is unchanged. The language scenario fails the initial import and another retry, then restores the network and checks successful recovery. Hosted results must refer to the exact candidate head; a prior successful source check does not substitute for browser recovery evidence.
