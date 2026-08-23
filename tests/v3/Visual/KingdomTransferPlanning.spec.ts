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

  const northstar = page.getByRole('heading', { name: 'Northstar Marshal', level: 2 });
  const ember = page.getByRole('heading', { name: 'Ember Vanguard', level: 2 });
  const frost = page.getByRole('heading', { name: 'Frost Envoy', level: 2 });

  await expect(northstar).toBeVisible();
  await expect(ember).toBeVisible();
  await expect(frost).toBeVisible();
  await expect(page.getByText('Eligible now', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Blocked', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Needs verification', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Transfer Group 7', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('K1524 Vanguard', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Confirm alliance hand-off time', { exact: true })).toBeVisible();

  const filter = page.getByRole('combobox', { name: /eligibility/i });
  await expect(filter).toBeVisible();
  await expect(filter).toHaveAccessibleName(/eligibility/i);
  await filter.focus();
  await page.keyboard.press('ArrowDown');
  await expect(filter).toHaveValue('eligible_now');
  await expect(northstar).toBeVisible();
  await expect(ember).toBeHidden();
  await page.keyboard.press('Home');
  await expect(filter).toHaveValue('all');
  await expect(ember).toBeVisible();

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
