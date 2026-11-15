import { readFileSync } from 'node:fs';
import { expect, test, type BrowserContext, type Page } from '@playwright/test';

const cookie = 'kingshot_locale_state';
const storage = 'kingshot.locale.preference';
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8')) as Record<string, { file: string }>;
const chunk = (domain: string, locale: string) => `**/build/${manifest[`resources/js/localization/messages/${domain}/${locale}.ts`]!.file}*`;
const select = (page: Page) => page.locator('select').filter({ has: page.locator('option[value="pt-BR"]') }).first();
const preference = async (context: BrowserContext) => (await context.cookies()).find((item) => item.name === cookie)!.value;
const change = async (page: Page, locale: string) => {
  await select(page).selectOption(locale);
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(select(page)).toBeEnabled();
};

declare global {
  interface Window { heldLocaleEvents: string[]; holdLocaleEvents: boolean }
}

test.use({ serviceWorkers: 'block' });

test('startup honors the server cookie over both stale localStorage formats', async ({ page, context, baseURL }) => {
  const state = `1.${'a'.repeat(32)}.4.${'b'.repeat(32)}.fr`;
  await context.addCookies([
    { name: cookie, value: state, url: baseURL! },
    { name: 'kingshot_locale', value: 'ar', url: baseURL! },
  ]);
  await page.addInitScript(({ storage }) => {
    localStorage.setItem('kingshot.locale', 'ar');
    localStorage.setItem(storage, `1.${'a'.repeat(32)}.99.${'c'.repeat(32)}.ar`);
  }, { storage });
  const response = await page.goto('/');
  expect(await response!.text()).toMatch(/<html[^>]+lang="fr"/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  await expect(page.locator('h1')).toContainText('Coordonnez votre alliance.');
  expect(await preference(context)).toBe(state);
});

test('two tabs reject delayed ABA intent and reconcile opposite storage-event orders', async ({ page, context }) => {
  await context.addInitScript(() => {
    window.heldLocaleEvents = [];
    window.holdLocaleEvents = true;
    window.addEventListener('storage', (event) => {
      if (window.holdLocaleEvents && event.isTrusted && event.key === 'kingshot.locale.preference') {
        event.stopImmediatePropagation();
        if (event.newValue) window.heldLocaleEvents.push(event.newValue);
      }
    }, true);
  });
  await page.goto('/');
  const peer = await context.newPage();
  await peer.goto('/');
  await expect(select(page)).toHaveValue('en');
  await expect(select(peer)).toHaveValue('en');
  let release!: () => void;
  const gate = new Promise<void>((resolve) => { release = resolve; });
  let delayed = false;
  await page.route(chunk('public', 'fr'), async (route) => { delayed = true; await gate; await route.continue(); });
  await select(page).selectOption('fr');
  await expect.poll(() => delayed).toBe(true);
  await change(peer, 'ar');
  const arabic = await preference(context);
  await change(peer, 'en');
  const english = await preference(context);
  expect(english).not.toBe(arabic);
  release();
  await expect(select(page)).toBeEnabled();
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  for (const [target, values] of [[page, [english, arabic]], [peer, [arabic, english]]] as const) {
    await target.evaluate(({ storage, values }) => {
      window.holdLocaleEvents = false;
      for (const newValue of values) window.dispatchEvent(new StorageEvent('storage', { key: storage, newValue }));
    }, { storage, values });
  }
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(peer.locator('html')).toHaveAttribute('lang', 'en');
  expect(await preference(context)).toBe(english);
  await peer.close();
});

test('remote failure preserves displayed language and retries the latest shared choice', async ({ page, context }) => {
  await page.goto('/');
  const peer = await context.newPage();
  await peer.goto('/');
  await page.route(chunk('public', 'fr'), (route) => route.abort('failed'));
  await change(peer, 'fr');
  await expect(page.getByRole('alert')).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  const response = await context.request.get('/login');
  expect(await response.text()).toMatch(/<html[^>]+lang="fr"/);
  // Pause automatic reconciliation so this journey specifically exercises retry.
  // Clicking the background tab otherwise legitimately reconciles on window focus.
  await page.evaluate(() => {
    const hold = (event: Event) => event.stopImmediatePropagation();
    for (const name of ['storage', 'focus', 'pageshow']) window.addEventListener(name, hold, { capture: true });
    document.addEventListener('visibilitychange', hold, { capture: true });
  });
  await change(peer, 'ar');
  await page.getByRole('button', { name: 'Try again' }).click();
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByRole('alert')).toHaveCount(0);
  expect(await preference(context)).toMatch(/\.ar$/);
  await peer.close();
});

test('two tabs tolerate blocked storage writes and blocked cookie writes', async ({ page, context }) => {
  await page.addInitScript(() => {
    Storage.prototype.setItem = () => { throw new DOMException('Storage unavailable', 'SecurityError'); };
  });
  await page.goto('/');
  const peer = await context.newPage();
  await peer.goto('/');
  await change(page, 'fr');
  await peer.evaluate(() => window.dispatchEvent(new Event('focus')));
  await expect(peer.locator('html')).toHaveAttribute('lang', 'fr');
  const serverPreference = await preference(context);
  await page.evaluate(() => {
    const descriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie')!;
    Object.defineProperty(document, 'cookie', { get: () => descriptor.get!.call(document), set: () => undefined });
  });
  await change(page, 'ar');
  expect(await preference(context)).toBe(serverPreference);
  await page.evaluate(() => window.dispatchEvent(new Event('focus')));
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(peer.locator('html')).toHaveAttribute('lang', 'fr');
  await change(peer, 'en');
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await peer.close();
});

test('startup catalogue failure mounts English and offers a real retry without changing the cookie', async ({ page, context, baseURL }) => {
  const state = `1.${'a'.repeat(32)}.2.${'c'.repeat(32)}.fr`;
  await context.addCookies([{ name: cookie, value: state, url: baseURL! }]);
  const pattern = chunk('core', 'fr');
  await page.route(pattern, (route) => route.abort('failed'));
  await page.goto('/');
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible();
  expect(await preference(context)).toBe(state);
  await page.unroute(pattern);
  await page.getByRole('button', { name: 'Try again' }).click();
  await expect(page.locator('h1')).toContainText('Coordonnez votre alliance.');
  expect(await preference(context)).toBe(state);
});
