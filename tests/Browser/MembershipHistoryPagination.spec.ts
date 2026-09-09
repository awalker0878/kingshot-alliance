import { expect, test } from '@playwright/test';

test('member history opens from its profile and reaches older records', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill('recruitment-history-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  const switcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (
    (await switcher.isVisible()) &&
    /select governor/i.test((await switcher.textContent()) ?? '')
  ) {
    await switcher.click();
    await page
      .getByRole('listbox', { name: 'Active Governor' })
      .getByRole('option')
      .first()
      .click();
    await page.waitForURL('**/dashboard');
  }
  await page.goto('/alliance/roster/intelligence');
  await page.locator('a[href^="/alliance/roster/"][href$="/history"]:visible').first().click();
  const profile = page.locator('section[aria-labelledby="member-capability-profile"]');
  await expect(
    profile.getByText('12 records on this page (up to 12).', { exact: true }),
  ).toBeVisible();
  await profile.locator('a[href^="/alliance/members/"][href$="/history"]').click();
  const history = page.locator('section[aria-labelledby="member-history-list"]');
  await expect(history.locator('article')).toHaveCount(50);
  const firstIds = await history
    .locator('article')
    .evaluateAll((nodes) => nodes.map((node) => node.textContent));
  await history.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(history.locator('article')).toHaveCount(5);
  await expect(history.getByRole('button', { name: 'Next page', exact: true })).toHaveCount(0);
  await history.getByRole('link', { name: 'First page', exact: true }).click();
  await expect(history.locator('article')).toHaveCount(50);
  expect(
    await history.locator('article').evaluateAll((nodes) => nodes.map((node) => node.textContent)),
  ).toEqual(firstIds);
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1),
  ).toBe(false);
  expect(errors).toEqual([]);
});
