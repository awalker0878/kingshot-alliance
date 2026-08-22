import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const territoryVisualFingerprints: Record<string, string> = {
  desktop: '57f1dbd12042e95876ab7930690829e1cbf90bc762525f4ecb88f71ca6b26974',
  mobile: 'ee05cc20c4afa6addd48ce85aebdfb0d0789150b249659f00615862161dc8312',
};

async function activateVisualGovernor(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('territory-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (await identitySwitcher.isVisible()) {
    const label = (await identitySwitcher.textContent()) ?? '';
    if (/select governor/i.test(label)) {
      await identitySwitcher.click();
      await page.getByRole('listbox', { name: 'Active Governor' }).getByRole('option').first().click();
      await page.waitForURL('**/dashboard');
    }
  }
}

test('Territory Command renders the saved hive without horizontal page overflow', async (
  { page },
  testInfo,
) => {
  await activateVisualGovernor(page);
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Territory Command' })).toBeVisible();
  await expect(page.getByText('Bear Hive Alpha')).toBeVisible();
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  await page.getByText('Bear Hive Alpha').click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Hive Builder' })).toBeVisible();
  await expect(page.getByLabel('Interactive Kingdom territory map editor')).toBeVisible();

  const marchAnalysis = page.getByRole('region', { name: 'Governor march analysis' });
  await expect(marchAnalysis).toBeVisible();
  await expect(marchAnalysis.getByRole('cell', { name: 'North Star' })).toBeVisible();
  await expect(marchAnalysis.getByRole('cell', { name: 'Bear Trap 1' })).toBeVisible();

  const editorOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(editorOverflow).toBeFalsy();

  const expectedFingerprint = territoryVisualFingerprints[testInfo.project.name];
  expect(expectedFingerprint, `Missing Territory visual fingerprint for ${testInfo.project.name}`).toBeDefined();

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('territory-planner.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');

  expect(actualFingerprint).toBe(expectedFingerprint);
});
