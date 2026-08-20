# Mobile and PWA

Status: Current

Kingshot Alliance is installable as a Progressive Web App on browsers that expose the standard install prompt. The authenticated and public layouts share one accessible status surface for install availability, connection loss, and application updates.

## Application assets

- `/manifest.webmanifest` defines the standalone experience, theme, icons, and authenticated shortcuts.
- `/service-worker.js` owns the public static-asset cache and update lifecycle.
- `/offline.html` is the only offline document.
- 192px and 512px PNG icons support install and maskable launch surfaces.

The client registers the worker only in production. A waiting worker is activated only after the Governor chooses **Update now**, then the page reloads under the new controller.

## Privacy boundary

The worker caches only:

- the generic offline page, manifest, and application icons;
- content-hashed Vite assets under `/build/assets/`;
- public images under `/images/`.

Every navigation remains network-first and falls back to the generic offline page. API, Inertia, JSON, account, Alliance, Event, Content, Recruitment, and notification responses are never added to Cache Storage. The fallback explicitly tells the Governor that private command data is unavailable offline.

This is intentional: offline private reads would require device storage controls, explicit retention, logout/context purging, and a threat model. Offline writes remain prohibited until conflict semantics exist.

## Browser behavior

Chromium-family browsers surface the in-product **Install app** action when eligible. Browsers that do not emit `beforeinstallprompt` continue to work normally and may expose installation through their own menu. Connection and update states remain keyboard accessible and use a polite live region.
