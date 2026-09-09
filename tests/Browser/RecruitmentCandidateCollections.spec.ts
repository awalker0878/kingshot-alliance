import { expect, test } from '@playwright/test';

test('candidate collections retain drafts, search selections and recover from lookup failures', async ({
  page,
}, testInfo) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page
    .locator('#email')
    .fill(`recruitment-collections-${testInfo.project.name}@example.test`);
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
    .getByRole('link', { name: `Collection candidate ${testInfo.project.name}`, exact: true })
    .click();
  const notes = page.locator('section[aria-labelledby="notes-heading"]');
  const tags = page.locator('section[aria-labelledby="tags-heading"]');
  const reviewers = page.locator('form').filter({ has: page.locator('#reviewers-heading') });
  const draft = 'Keep my candidate note while paging';
  await notes.locator('textarea').fill(draft);
  for (const section of [tags, reviewers]) {
    await expect(section.locator('span.ks-chip')).toHaveCount(25);
    await section
      .getByRole('navigation', { name: 'Pagination' })
      .getByRole('button', { name: 'Next page' })
      .click();
    await expect(section.locator('span.ks-chip')).toHaveCount(25);
    await expect(section.locator('span.ks-chip').first()).toHaveText('Collection choice 025');
    await section
      .getByRole('navigation', { name: 'Pagination' })
      .getByRole('button', { name: 'Next page' })
      .click();
    await expect(section.locator('span.ks-chip')).toHaveCount(5);
    await expect(section.locator('span.ks-chip').last()).toHaveText('Collection choice 054');
  }
  await expect(notes.locator('textarea')).toHaveValue(draft);
  let failNextLookup = true;
  await page.route('**/options/members?**', async (route) => {
    if (failNextLookup) {
      failNextLookup = false;
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    } else await route.continue();
  });
  for (const id of ['reviewer-picker', 'conversion-player-picker', 'decision-template-picker']) {
    const select = page.locator(`#${id}`);
    const picker = select.locator('..');
    const search = page.locator(`#${id}-search`);
    await search.fill('Collection choice');
    await picker.getByRole('button', { name: 'Search', exact: true }).click();
    if (id === 'reviewer-picker') {
      await expect(picker.getByRole('alert')).toHaveText('Could not load choices. Try again.');
      await picker.getByRole('button', { name: 'Search', exact: true }).click();
      await expect(picker.getByRole('alert')).toHaveCount(0);
    }
    await expect(select.locator('option:not([value=""])')).toHaveCount(25);
    if (id === 'reviewer-picker') await expect(select.locator('option').nth(1)).toContainText('R3');
    await picker.getByRole('button', { name: 'Next page', exact: true }).click();
    await expect(select.locator('option').nth(1)).toContainText('Collection choice 025');
    await picker.getByRole('button', { name: 'Next page', exact: true }).click();
    await expect(select.locator('option:not([value=""])')).toHaveCount(5);
    const selectedId = await select.locator('option').last().getAttribute('value');
    expect(selectedId).toBeTruthy();
    await select.selectOption(selectedId!);
    await picker.getByRole('button', { name: 'First page', exact: true }).click();
    await expect(select).toHaveValue(selectedId!);
    await expect(select.locator('option:not([value=""])')).toHaveCount(26);
    await search.fill('choice 050');
    await search.press('Enter');
    await expect(select.locator('option:not([value=""])')).toHaveCount(2);
    await expect(select).toHaveValue(selectedId!);
    await search.fill('%_');
    await search.press('Enter');
    await expect(picker).toContainText('No matching choices.');
    await expect(select).toHaveValue(selectedId!);
  }
  await expect(notes.locator('textarea')).toHaveValue(draft);
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1),
  ).toBe(false);
  expect(errors).toEqual([]);
});
