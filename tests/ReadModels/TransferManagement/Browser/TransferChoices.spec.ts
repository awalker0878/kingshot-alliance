import { expect, test } from '@playwright/test';

test('transfer selectors reach every choice and retain drafts through failed pages and off-page selection', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`transfer-choices-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/alliance/transfers/manage');
  const coordinator = page
    .getByTestId('transfer-choice-picker')
    .filter({ has: page.locator('#transfer-new-coordinator') });
  await coordinator.getByRole('searchbox').fill('Fixture choice');
  await coordinator.getByRole('button', { name: 'Search choices', exact: true }).click();
  await expect(coordinator).toContainText('25 choices on this page; 55 match.');
  await coordinator.locator('select').selectOption({ label: 'Fixture choice 000' });
  const firstValue = await coordinator.locator('select').inputValue();
  await page.getByPlaceholder('Cohort name', { exact: true }).fill('Unsaved selection draft');
  await page.route('**/alliance/transfers/manage/choices/coordinators?*', async (route) => {
    if (new URL(route.request().url()).searchParams.has('cursor')) {
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
      await page.unroute('**/alliance/transfers/manage/choices/coordinators?*');
    } else await route.continue();
  });
  await coordinator.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(coordinator.getByRole('alert')).toContainText('Choices could not be loaded.');
  await expect(coordinator.locator('select')).toHaveValue(firstValue);
  await expect(page.getByPlaceholder('Cohort name', { exact: true })).toHaveValue(
    'Unsaved selection draft',
  );
  await coordinator.getByRole('button', { name: 'Retry choices', exact: true }).click();
  await expect(coordinator.locator('option', { hasText: 'Fixture choice 025' })).toHaveCount(1);
  await expect(coordinator.locator('select')).toHaveValue(firstValue);
  await coordinator.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(coordinator).toContainText('5 choices on this page; 55 match.');
  await coordinator.locator('select').selectOption({ label: 'Fixture choice 054' });
  const chosen = await coordinator.locator('select').inputValue();
  await coordinator.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(coordinator).toContainText('25 choices on this page; 55 match.');
  await expect(coordinator.locator('select')).toHaveValue(chosen);
  await expect(coordinator.locator('option:checked')).toHaveText('Fixture choice 054');
  await page.getByRole('button', { name: 'Create cohort', exact: true }).click();
  // The persisted cohort keeps its selected coordinator even though that choice is off page.
  const savedChoice = page.locator('select[id^="transfer-coordinator-"]');
  await expect(savedChoice).toHaveCount(1);
  await expect(savedChoice).toHaveValue(chosen);
  await expect(savedChoice.locator('option:checked')).toHaveText('Fixture choice 054');
  const roster = page
    .getByTestId('transfer-choice-picker')
    .filter({ has: page.locator('#transfer-new-roster') });
  await roster.getByRole('searchbox').fill('Fixture choice 054');
  await roster.getByRole('button', { name: 'Search choices', exact: true }).click();
  await roster.locator('select').selectOption({ label: 'Fixture choice 054' });
  await page.getByRole('button', { name: 'Add Governor', exact: true }).click();
  await expect(
    page
      .locator('article')
      .filter({ has: page.getByText('Fixture choice 054', { exact: true }) })
      .last(),
  ).toBeVisible();
  const windows = page
    .getByTestId('transfer-choice-picker')
    .filter({ has: page.locator('#transfer-plan-window') });
  await windows.getByRole('searchbox').fill('Fixture choice 054');
  await windows.getByRole('button', { name: 'Search choices', exact: true }).click();
  await windows.locator('select').selectOption({ label: 'Fixture choice 054' });
  await expect(windows.locator('option:checked')).toHaveText('Fixture choice 054');
  expect(errors).toEqual([]);
});
