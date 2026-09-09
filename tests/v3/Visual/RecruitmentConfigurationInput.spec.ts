import { expect, test } from '@playwright/test';

test('question validation preserves drafts and newly created questions can be edited', async ({
  page,
}, testInfo) => {
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
  await page.goto('/alliance/recruitment');
  const section = page.locator('section[aria-labelledby="questions-heading"]');
  const form = section.locator('form');
  const prompt = `Input boundary ${testInfo.project.name}`;
  await expect(page.locator('#question-prompt')).toHaveAttribute('maxlength', '240');
  await expect(page.locator('#question-help')).toHaveAttribute('maxlength', '2000');
  await page.locator('#question-prompt').fill(prompt);
  await page.locator('#question-type').selectOption('multi_select');
  const oversized = Array.from({ length: 31 }, (_, i) => `Choice ${i}`).join('\n');
  await page.locator('#question-options').fill(oversized);
  await form.locator('button[type="submit"]').click();
  await expect(form.getByRole('alert')).toBeVisible();
  await expect(page.locator('#question-prompt')).toHaveValue(prompt);
  await expect(page.locator('#question-options')).toHaveValue(oversized);
  await page.locator('#question-options').fill('First\nSecond');
  await form.locator('button[type="submit"]').click();
  const row = section.locator('details').filter({ hasText: prompt });
  await expect(row.locator('summary')).toContainText(prompt);
  await row.locator('summary').click();
  await row.locator('input:not([type])').fill(`Updated ${prompt}`);
  await row.locator('textarea').last().fill(oversized);
  await row.getByRole('button').click();
  await expect(row.getByRole('alert')).toBeVisible();
  await expect(row.locator('input:not([type])')).toHaveValue(`Updated ${prompt}`);
  await row.locator('textarea').last().fill('First\nSecond');
  await row.getByRole('button').click();
  await expect(row.locator('summary')).toContainText(`Updated ${prompt}`);
  await expect(row.getByRole('alert')).toHaveCount(0);
  expect(errors).toEqual([]);
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1),
  ).toBe(false);
});
