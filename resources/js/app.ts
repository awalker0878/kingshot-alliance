import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

import {
  AUTHORITY_CONTEXT_STALE_EVENT,
  authorityContextHeaders,
  authorityContextKey,
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

void bootstrap();
