import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const screenshotIntakeVisualFingerprints: Record<string, string> = {
  desktop: 'f3a8e287e5fb39fe7230cd176909877cf427d19d1da639b19749c615e8aeb875',
  mobile: '4a8b7c237f5c2aab5956dde17b8105476631d666f8ce660abbac991e1a5b99f8',
};

async function openScreenshotIntake(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('screenshot-visual@example.test');
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

  await page.goto('/events');
  await page.getByRole('link', { name: /Bear Hunt · Visual Review/ }).first().click();
  await page.getByRole('link', { name: 'Import battle report' }).click();
  await page.waitForLoadState('networkidle');
}

test('Screenshot Intake keeps evidence and review controls accessible without page overflow', async (
  { page },
  testInfo,
) => {
  await openScreenshotIntake(page);
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Screenshot Intake', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Captured report values' })).toBeVisible();
  await expect(page.getByText('raging-bear-battle-record.png')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Score preview' })).toBeVisible();
  await expect(page.getByRole('combobox', { name: 'Matched Governor' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Save to Bear Hunt results' })).toBeVisible();

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('screenshot-intake.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');
  const expectedFingerprint = screenshotIntakeVisualFingerprints[testInfo.project.name];

  expect(
    actualFingerprint,
    `Update Screenshot Intake visual fingerprint for ${testInfo.project.name}`,
  ).toBe(expectedFingerprint);
});
