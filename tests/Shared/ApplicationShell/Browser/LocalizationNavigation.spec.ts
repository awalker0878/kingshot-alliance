import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8')) as Record<string, { file: string }>;
const chunk = (domain: string, locale: string) => `/build/${manifest[`resources/js/localization/messages/${domain}/${locale}.ts`]!.file}`;

// Page routing cannot intercept requests made by the production service worker.
// Isolate the network-failure boundary while retaining the real built modules.
test.use({ serviceWorkers: 'block' });

test('failed navigation chunk keeps the displayed home page translatable', async ({ page }) => {
  let blocked = false;
  await page.route(`**${chunk('auth', 'en')}`, async route => {
    blocked = true;
    await route.abort('failed');
  });
  await page.goto('/');
  await expect(page.locator('h1')).toContainText('Gather your Alliance.');
  // The real Inertia request reaches the real page resolver; only its new
  // localization-domain import fails. Home must remain the accepted page.
  await page.locator('header a[href="/login"]').click();
  await expect.poll(() => blocked).toBe(true);
  await expect(page.locator('h1')).toContainText('Gather your Alliance.');
  await page.locator('select').first().selectOption('fr');
  await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  await expect(page.locator('h1')).toContainText('Coordonnez votre alliance.');
  await expect(page.locator('h1')).not.toContainText('home.heroLine1');
});

test('failed language chunk restores selection and provides accessible retry', async ({ page }) => {
  let blocked = false;
  await page.route(`**${chunk('public', 'fr')}`, async route => {
    blocked = true;
    await route.abort('failed');
  });
  await page.goto('/');
  const select = page.locator('select').first();
  await expect(select).toHaveValue('en');
  await select.selectOption('fr');
  await expect.poll(() => blocked).toBe(true);
  await expect(select).toHaveValue('en');
  await expect(select).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByRole('alert')).toContainText('Language');
  await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  expect(await page.evaluate(() => localStorage.getItem('kingshot.locale'))).not.toBe('fr');
  await page.unroute(`**${chunk('public', 'fr')}`);
  await page.getByRole('button', { name: 'Try again' }).click();
  await expect(select).toHaveValue('fr');
  await expect(page.locator('h1')).toContainText('Coordonnez votre alliance.');
});
