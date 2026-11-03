import { expect, test } from '@playwright/test';

const rooms = [
  {
    scene: 'territory-command',
    path: '/territory/explore',
    email: 'territory-visual@example.test',
  },
  {
    scene: 'gift-code-vault',
    path: '/gift-codes',
    email: 'gift-code-catalogue-visual@example.test',
  },
  { scene: 'progression-forge', path: '/progression', email: 'ux-p9-visual@example.test' },
  { scene: 'assistant-archive', path: '/assistant', email: 'screenshot-visual@example.test' },
];

for (const room of rooms) {
  test(`@room-art ${room.scene} loads responsive artwork behind localized content`, async ({
    page,
  }, testInfo) => {
    await page.goto('/login');
    await page.locator('#email').fill(room.email);
    await page.locator('#password').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/dashboard');
    await page.waitForLoadState('networkidle');
    const switcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
    // Activate the fixture Governor through the UI regardless of the browser's
    // startup language. English labels do not identify the Arabic switcher.
    if (await switcher.isVisible()) {
      await switcher.click();
      await page.getByRole('listbox').getByRole('option').first().click();
      await page.waitForLoadState('networkidle');
    }

    const locales = testInfo.project.name === 'rtl-tablet' ? ['ar'] : ['en', 'fr', 'pt-BR', 'ar'];
    for (const locale of locales) {
      await page.evaluate((value) => window.localStorage.setItem('kingshot.locale', value), locale);
      await page.goto(room.path);
      await page.waitForLoadState('networkidle');
      await page.evaluate(() => document.fonts.ready);
      await expect(page.locator('html')).toHaveAttribute('lang', locale);
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');

      const picture = page.locator(`[data-room-scene="${room.scene}"]`);
      const image = picture.locator('img');
      await expect(picture).toHaveAttribute('aria-hidden', 'true');
      await expect(image).toHaveAttribute('alt', '');
      await expect
        .poll(() =>
          image.evaluate(
            (element: HTMLImageElement) => element.complete && element.naturalWidth > 0,
          ),
        )
        .toBe(true);
      const mobile = (page.viewportSize()?.width ?? 1440) < 640;
      const expectedFile = `${room.scene}-${mobile ? 'mobile' : 'desktop'}.webp`;
      expect(await image.evaluate((element: HTMLImageElement) => element.currentSrc)).toContain(
        expectedFile,
      );
      expect(
        await image.evaluate((element: HTMLImageElement) => [
          element.naturalWidth,
          element.naturalHeight,
        ]),
      ).toEqual(mobile ? [768, 1024] : [1280, 480]);
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
        ),
      ).toBe(false);

      // Capture the actual page header for review, with the production scrim and fonts.
      await picture.locator('..').screenshot({
        path: testInfo.outputPath(`room-art-${room.scene}-${locale}.png`),
        animations: 'disabled',
        scale: 'css',
      });
    }
  });
}
