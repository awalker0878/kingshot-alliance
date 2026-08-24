import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const territoryVisualFingerprints: Record<string, string> = {
  desktop: 'e7ea88d18e0b28e6bdc1014717b49c78e7f0ca4f23089bc05be34e2bad0530b8',
  mobile: '0fa34fb492a3d8ec368f41db1908a19264334b439c047b07fcbd3ed3826d403c',
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

  await page.getByRole('link', { name: 'Hive Builder' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Hive Builder' })).toBeVisible();
  await expect(
    page.getByLabel('Interactive Kingdom territory map editor', { exact: true }),
  ).toBeVisible();

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
