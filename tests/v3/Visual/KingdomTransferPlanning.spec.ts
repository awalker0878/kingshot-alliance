import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const transferVisualFingerprints: Record<string, string> = {
  desktop: 'PENDING_DESKTOP',
  mobile: 'PENDING_MOBILE',
};

async function openTransferPlanning(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('transfer-visual@example.test');
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

  await page.goto('/alliance/transfers/readiness');
  await page.waitForLoadState('networkidle');
}

test('Kingdom Transfer Planning keeps eligibility, verification, and readiness distinct', async (
  { page },
  testInfo,
) => {
  await openTransferPlanning(page);
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByText('Northstar Marshal', { exact: true })).toBeVisible();
  await expect(page.getByText('Ember Vanguard', { exact: true })).toBeVisible();
  await expect(page.getByText('Frost Envoy', { exact: true })).toBeVisible();
  await expect(page.getByText('Eligible now', { exact: true })).toBeVisible();
  await expect(page.getByText('Blocked', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Needs verification', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Transfer Group 7', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('K1524 Vanguard', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Confirm alliance hand-off time', { exact: true })).toBeVisible();

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  await page
    .locator('p')
    .filter({ hasText: /^Evaluated/ })
    .evaluateAll((elements) => elements.forEach((element) => (element.textContent = 'Evaluated at fixture time')));

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('kingdom-transfer-planning.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');
  const expectedFingerprint = transferVisualFingerprints[testInfo.project.name];

  expect(
    actualFingerprint,
    `Update Kingdom Transfer Planning visual fingerprint for ${testInfo.project.name}`,
  ).toBe(expectedFingerprint);
});
