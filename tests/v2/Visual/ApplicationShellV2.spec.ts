import { expect, test } from '@playwright/test';

const publicSurfaces = [
  { path: '/', name: 'realm-gate' },
  { path: '/login', name: 'sign-in' },
  { path: '/register', name: 'registration' },
] as const;

for (const surface of publicSurfaces) {
  test(`${surface.name} renders without overflow and matches its visual baseline`, async ({ page }) => {
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
