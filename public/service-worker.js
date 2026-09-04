const CACHE_NAME = 'kingshot-public-assets-v1';
const OFFLINE_FALLBACK = '/offline.html';
const PRECACHE = [
  OFFLINE_FALLBACK,
  '/manifest.webmanifest',
  '/images/app-icons/icon-192.png',
  '/images/app-icons/icon-512.png',
];
const PUBLIC_ASSET_PREFIXES = ['/build/assets/', '/images/'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith('kingshot-public-assets-') && key !== CACHE_NAME)
            .map((key) => caches.delete(key)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data?.json() ?? {};
  } catch {
    payload = {};
  }

  const title = typeof payload.title === 'string' && payload.title.trim() !== ''
    ? payload.title.slice(0, 200)
    : 'Kingshot Alliance';
  const body = typeof payload.body === 'string' ? payload.body.slice(0, 1200) : undefined;
  const candidate = typeof payload.action_url === 'string' ? payload.action_url : '/notifications';
  const actionUrl = candidate.startsWith('/') && !candidate.startsWith('//')
    ? candidate
    : '/notifications';

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: '/images/app-icons/icon-192.png',
      badge: '/images/app-icons/icon-192.png',
      data: { actionUrl },
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const candidate = event.notification.data?.actionUrl;
  const actionUrl = typeof candidate === 'string' && candidate.startsWith('/') && !candidate.startsWith('//')
    ? candidate
    : '/notifications';
  const target = new URL(actionUrl, self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      const existing = clients.find((client) => client.url === target);
      if (existing) return existing.focus();
      return self.clients.openWindow(target);
    }),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET' || request.headers.has('range')) return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // HTML, Inertia, JSON, and API responses are always network-only. The only
  // offline document is the generic fallback, which contains no Alliance data.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(async () => {
        const fallback = await caches.match(OFFLINE_FALLBACK);
        return (
          fallback ??
          new Response('Kingshot Alliance is offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
          })
        );
      }),
    );
    return;
  }

  const isPublicAsset =
    PRECACHE.includes(url.pathname) ||
    PUBLIC_ASSET_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
  if (!isPublicAsset) return;

  event.respondWith(
    caches.open(CACHE_NAME).then(async (cache) => {
      const cached = await cache.match(request);
      const network = fetch(request)
        .then((response) => {
          if (response.ok && response.type === 'basic') {
            void cache.put(request, response.clone());
          }
          return response;
        })
        .catch(() => null);

      return cached ?? (await network) ?? Response.error();
    }),
  );
});
