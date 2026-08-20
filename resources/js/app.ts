import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

import {
  AUTHORITY_CONTEXT_HEADER,
  AUTHORITY_CONTEXT_STALE_EVENT,
  authorityContextHeaders,
  authorityContextKey,
  authorityContextVersion,
  dispatchAuthorityContextStale,
  isAuthorityContextStaleResponse,
  updateAuthorityContextFromPageProps,
} from './identity/authority-context';
import {
  beginContextTransition,
  cancelContextTransition,
  completeContextTransition,
} from './identity/context-isolation';
import { ensurePageDomains, initializeLocale, t } from './localization';

const appName = import.meta.env.VITE_APP_NAME ?? 'Kingshot Alliance';
const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { import: 'default' });
let recoveringAuthorityContext = false;
let authorityNoticeTimer: number | null = null;
let fetchInterceptorInstalled = false;

async function bootstrap(): Promise<void> {
  await initializeLocale();

  await createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
      const page = pages[`./pages/${name}.vue`];
      if (!page) throw new Error(`Page not found: ${name}`);

      await ensurePageDomains(name);
      return page();
    },
    defaults: {
      visitOptions: (_href, options) => ({
        headers: authorityContextHeaders(options.headers),
      }),
    },
    setup({ el, App, props, plugin }) {
      updateAuthorityContextFromPageProps(props.initialPage.props as Record<string, unknown>);
      installAuthorityContextRuntime();

      createApp({ render: () => h(App, props) })
        .use(plugin)
        .mount(el);
    },
    progress: { color: '#e2b44d' },
  });
}

function installAuthorityContextRuntime(): void {
  installFetchInterceptor();

  router.on('navigate', (event) => {
    updateAuthorityContextFromPageProps(event.detail.page.props as Record<string, unknown>);
  });

  router.on('httpException', (event) => {
    if (!isAuthorityContextStaleResponse(event.detail.response)) return;

    event.preventDefault();
    dispatchAuthorityContextStale();
  });

  window.addEventListener(AUTHORITY_CONTEXT_STALE_EVENT, recoverFromStaleAuthorityContext);
}

function installFetchInterceptor(): void {
  if (fetchInterceptorInstalled) return;
  fetchInterceptorInstalled = true;

  const nativeFetch = window.fetch.bind(window);
  window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const method = (
      init?.method ?? (input instanceof Request ? input.method : 'GET')
    ).toUpperCase();
    const version = authorityContextVersion();
    const shouldAttach =
      version !== null &&
      !['GET', 'HEAD', 'OPTIONS'].includes(method) &&
      isSameOriginRequest(input);

    let requestInit = init;
    if (shouldAttach) {
      const headers = new Headers(input instanceof Request ? input.headers : undefined);
      new Headers(init?.headers).forEach((value, key) => headers.set(key, value));
      headers.set(AUTHORITY_CONTEXT_HEADER, version);
      requestInit = { ...init, headers };
    }

    const response = await nativeFetch(input, requestInit);
    if (isAuthorityContextStaleResponse(response)) dispatchAuthorityContextStale();

    return response;
  };
}

function isSameOriginRequest(input: RequestInfo | URL): boolean {
  const value = input instanceof Request ? input.url : input.toString();

  try {
    return new URL(value, window.location.href).origin === window.location.origin;
  } catch {
    return false;
  }
}

function recoverFromStaleAuthorityContext(): void {
  if (recoveringAuthorityContext) return;

  recoveringAuthorityContext = true;
  const staleContextKey = authorityContextKey();
  let recovered = false;

  beginContextTransition(staleContextKey);
  showAuthorityContextStaleNotice();

  router.visit('/dashboard', {
    method: 'get',
    replace: true,
    preserveState: false,
    preserveScroll: false,
    onSuccess: (page) => {
      updateAuthorityContextFromPageProps(page.props as Record<string, unknown>);
      completeContextTransition(staleContextKey);
      recovered = true;
    },
    onFinish: () => {
      if (!recovered) cancelContextTransition(staleContextKey);
      recoveringAuthorityContext = false;
    },
  });
}

function showAuthorityContextStaleNotice(): void {
  const id = 'authority-context-stale-notice';
  let notice = document.getElementById(id);

  if (!notice) {
    notice = document.createElement('div');
    notice.id = id;
    notice.setAttribute('role', 'alert');
    notice.setAttribute('aria-live', 'assertive');
    notice.className =
      'fixed end-4 top-4 z-[200] max-w-md rounded-lg border border-amber-400/40 bg-[#181108] px-4 py-3 text-sm font-semibold text-amber-100 shadow-2xl';
    document.body.appendChild(notice);
  }

  notice.textContent = t('common.contextStale');

  if (authorityNoticeTimer !== null) window.clearTimeout(authorityNoticeTimer);
  authorityNoticeTimer = window.setTimeout(() => {
    document.getElementById(id)?.remove();
    authorityNoticeTimer = null;
  }, 8000);
}

function registerServiceWorker(): void {
  if (!('serviceWorker' in navigator) || import.meta.env.DEV) return;

  const register = (): void => {
    void navigator.serviceWorker
      .register('/service-worker.js', { scope: '/', updateViaCache: 'none' })
      .then((registration) => {
        const announceUpdate = (): void => {
          if (!registration.waiting) return;

          window.dispatchEvent(
            new CustomEvent('pwa:update-available', {
              detail: { registration },
            }),
          );
        };

        announceUpdate();
        registration.addEventListener('updatefound', () => {
          const worker = registration.installing;
          worker?.addEventListener('statechange', () => {
            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
              announceUpdate();
            }
          });
        });
      })
      .catch((error: unknown) => {
        console.warn('PWA service worker registration failed.', error);
      });
  };

  if (document.readyState === 'complete') register();
  else window.addEventListener('load', register, { once: true });
}

void bootstrap();
registerServiceWorker();
