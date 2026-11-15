import { expect, type Page } from '@playwright/test';

/** Use the visible selector, opening the app's compact navigation when needed. */
export async function selectLocale(page: Page, locale: string): Promise<void> {
  const selector = page.locator('select:visible').filter({
    has: page.locator('option[value="pt-BR"]'),
  }).first();
  const openNavigation = await selector.count() === 0;
  if (openNavigation) await page.locator('header:visible > button').first().click();
  await selector.selectOption(locale);
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(selector).toBeEnabled();
  if (openNavigation) {
    await page.locator('aside:visible button').filter({ hasText: /^×$/ }).click();
  }
}
