import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const screenshotIntakeVisualFingerprints: Record<string, string> = {
  desktop: 'bde712043db23228231e0cd3c11ccdd47ba695d3c52c0263f11af87c2ec9c5b0',
  mobile: 'd0db74c2b0c3ae9332d099544ef5449379520d073bdee279192bca7a30293a0d',
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
  await page
    .locator('div.divide-y')
    .getByRole('link', { name: /Bear Hunt · Visual Review/ })
    .first()
    .click();
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
