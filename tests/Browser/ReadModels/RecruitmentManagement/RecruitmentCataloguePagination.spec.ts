import { expect, test } from '@playwright/test';

test('recruitment catalogues continue independently while retaining form drafts', async ({
  page,
}) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill('recruitment-catalogue-visual@example.test');
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
  await page.locator('#question-prompt').fill('Retain the unsaved question');
  for (const [heading, kind, rowSelector] of [
    ['questions-heading', 'question', 'details summary'],
    ['templates-heading', 'template', 'article strong'],
    ['onboarding-heading', 'onboarding', 'article strong'],
  ] as const) {
    const section = page.locator(`section[aria-labelledby="${heading}"]`);
    const rows = section.locator(rowSelector);
    await expect(rows).toHaveCount(25);
    const first = await rows.allTextContents();
    const names = [...first];
    await section.getByRole('button', { name: 'Next page', exact: true }).click();
    await expect(rows.first()).toContainText(`Catalogue ${kind} 025`);
    await expect(rows).toHaveCount(25);
    names.push(...(await rows.allTextContents()));
    await expect(page.locator('#question-prompt')).toHaveValue('Retain the unsaved question');
    await section.getByRole('button', { name: 'Next page', exact: true }).click();
    await expect(rows).toHaveCount(5);
    await expect(rows.first()).toContainText(`Catalogue ${kind} 050`);
    names.push(...(await rows.allTextContents()));
    expect(names).toHaveLength(55);
    expect(new Set(names).size).toBe(55);
    await expect(section.getByRole('button', { name: 'Next page', exact: true })).toHaveCount(0);
    await section.getByRole('link', { name: 'First page', exact: true }).click();
    await expect(rows).toHaveCount(25);
    expect(await rows.allTextContents()).toEqual(first);
    await expect(page.locator('#question-prompt')).toHaveValue('Retain the unsaved question');
  }
  expect(errors).toEqual([]);
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1),
  ).toBe(false);
});
