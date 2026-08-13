import { expect, test, type Page } from '@playwright/test';

const visualUser = {
  email: 'ux-p9-visual@example.test',
  password: 'password',
};

async function useLocale(page: Page, locale: string): Promise<void> {
  await page.addInitScript((value) => {
    window.localStorage.setItem('kingshot.locale', value);
  }, locale);
}

async function settle(page: Page): Promise<void> {
  await page.evaluate(async () => {
    await document.fonts.ready;
  });
}

async function signIn(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('input[type="email"]').fill(visualUser.email);
  await page.locator('input[type="password"]').fill(visualUser.password);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

for (const route of [
  { name: 'home', path: '/' },
  { name: 'login', path: '/login' },
  { name: 'register', path: '/register' },
  { name: 'forgot-password', path: '/forgot-password' },
]) {
  test(`${route.name} English baseline`, async ({ page }) => {
    await useLocale(page, 'en');
    await page.goto(route.path);
    await settle(page);

    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page).toHaveScreenshot(`${route.name}-en.png`, { fullPage: true });
  });
}

test('home Arabic RTL baseline', async ({ page }) => {
  await useLocale(page, 'ar');
  await page.goto('/');
  await settle(page);

  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page).toHaveScreenshot('home-ar.png', { fullPage: true });
});

test('login Arabic RTL baseline', async ({ page }) => {
  await useLocale(page, 'ar');
  await page.goto('/login');
  await settle(page);

  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page).toHaveScreenshot('login-ar.png', { fullPage: true });
});

test('authenticated application shell English baseline', async ({ page }) => {
  await useLocale(page, 'en');
  await signIn(page);
  await settle(page);

  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page).toHaveScreenshot('dashboard-en.png', { fullPage: true });
});

test('authenticated application shell Arabic RTL baseline', async ({ page }) => {
  await useLocale(page, 'en');
  await signIn(page);
  await page.evaluate(() => window.localStorage.setItem('kingshot.locale', 'ar'));
  await page.reload();
  await settle(page);

  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page).toHaveScreenshot('dashboard-ar.png', { fullPage: true });
});

test('keyboard skip link reaches the main application content', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'Keyboard focus traversal is asserted once on desktop.');

  await useLocale(page, 'en');
  await signIn(page);
  await page.keyboard.press('Tab');

  const skipLink = page.locator('a[href="#main-content"]');
  await expect(skipLink).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main-content')).toBeFocused();
});
