import { expect, test } from '@playwright/test';

test('candidate histories page independently and preserve the note draft', async ({
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
  await page
    .getByRole('link', { name: `History candidate ${testInfo.project.name}`, exact: true })
    .click();
  const notes = page.locator('section[aria-labelledby="notes-heading"]');
  const history = page.locator('section[aria-labelledby="history-heading"]');
  const communications = page.locator('section[aria-labelledby="communications-heading"]');
  const duplicates = page.locator('section[aria-labelledby="duplicates-heading"]');
  await expect(notes.locator('article')).toHaveCount(25);
  await expect(history.locator('li')).toHaveCount(25);
  await expect(communications.locator('article')).toHaveCount(25);
  await expect(duplicates.locator('article')).toHaveCount(25);
  const draft = 'Keep this unsaved recruitment note';
  await notes.locator('textarea').fill(draft);
  await notes.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(notes.locator('article')).toHaveCount(10);
  await expect(history.locator('li')).toHaveCount(25);
  await history.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(history.locator('li')).toHaveCount(10);
  await expect(notes.locator('article')).toHaveCount(10);
  await communications.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(communications.locator('article')).toHaveCount(10);
  await duplicates.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(duplicates.locator('article')).toHaveCount(10);
  await notes.getByRole('link', { name: 'First page', exact: true }).click();
  await expect(notes.locator('article')).toHaveCount(25);
  await expect(history.locator('li')).toHaveCount(10);
  await expect(communications.locator('article')).toHaveCount(10);
  await expect(duplicates.locator('article')).toHaveCount(10);
  await expect(notes.locator('textarea')).toHaveValue(draft);
  for (const section of [notes, history, communications, duplicates]) {
    await expect(
      section.getByRole('navigation', { name: 'Pagination', exact: true }),
    ).toBeVisible();
  }
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > window.innerWidth + 1,
  );
  expect(overflow).toBe(false);
  expect(errors).toEqual([]);
});
