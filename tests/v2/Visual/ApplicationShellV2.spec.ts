import { expect, test } from '@playwright/test';

test('V2 authentication shell renders without horizontal overflow', async ({ page }) => {
  const response = await page.goto('/login');
  expect(response?.ok()).toBeTruthy();
  await expect(page.locator('body')).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBeFalsy();
});
