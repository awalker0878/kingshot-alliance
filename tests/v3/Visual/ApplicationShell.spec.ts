import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';

const activeGovernorFingerprints: Record<string, string> = {
  desktop: '484f0bd7e8a95a2089c570e9206f690cfb98d31560030ef8fdbb9b433961cc4c',
  mobile: 'dea627c03e590e507e37abbc9e7276bd50520a6af7344797fe3e52d9b2ace0fe',
};

const publicSurfaces = [
  { path: '/', name: 'home' },
  { path: '/login', name: 'sign-in' },
  { path: '/register', name: 'registration' },
] as const;

for (const surface of publicSurfaces) {
  test(`${surface.name} renders without overflow and matches its visual baseline`, async ({
    page,
  }) => {
    const response = await page.goto(surface.path);
    expect(response?.ok()).toBeTruthy();

    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflow).toBeFalsy();

    await expect(page).toHaveScreenshot(`${surface.name}.png`, {
      fullPage: true,
    });
  });
}

test('multi-governor account selects and activates the first Governor', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.locator('#email').fill('ux-p9-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  await expect(identitySwitcher).toBeVisible();
  await expect(identitySwitcher).toContainText(/select governor/i);

  await expect(page).toHaveScreenshot('home-select-governor.png', {
    fullPage: true,
  });

  await identitySwitcher.click();
  const identityListbox = page.getByRole('listbox', { name: 'Active Governor' });
  const options = identityListbox.getByRole('option');
  await expect(identityListbox).toBeVisible();
  await expect(options).toHaveCount(2);
  await expect(options.nth(0)).toContainText('Lady Seraphina');
  await expect(options.nth(1)).toContainText('Lord Caspian');

  await expect(page).toHaveScreenshot('governor-switcher-open.png', {
    fullPage: true,
  });

  await options.nth(0).click();
  await page.waitForURL('**/dashboard');
  await page.waitForLoadState('networkidle');

  const activeIdentitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  await expect(activeIdentitySwitcher).toContainText('Lady Seraphina');
  await expect(activeIdentitySwitcher).toContainText('K1123');

  const activeGovernorScreenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    scale: 'css',
  });
  expect(createHash('sha256').update(activeGovernorScreenshot).digest('hex')).toBe(
    activeGovernorFingerprints[testInfo.project.name],
  );
});
