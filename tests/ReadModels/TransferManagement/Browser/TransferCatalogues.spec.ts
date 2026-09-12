import { expect, test } from '@playwright/test';

test('management catalogues retain drafts, reach history and page cohort assignments', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`transfer-catalogues-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/alliance/transfers/manage');
  const cohorts = page.locator('[data-transfer-catalogue="cohorts"]');
  await expect(cohorts).toContainText('55 records');
  await page.getByPlaceholder('Cohort name', { exact: true }).fill('Unsaved new cohort');
  const firstName = page.locator('article input').filter({ visible: true }).first();
  await firstName.fill('Unsaved existing cohort');
  const windows = page.locator('[data-transfer-catalogue="windows"]');
  await windows.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(page.getByPlaceholder('Cohort name', { exact: true })).toHaveValue(
    'Unsaved new cohort',
  );
  await expect(firstName).toHaveValue('Unsaved existing cohort');
  await page.route('**/alliance/transfers/manage?*', async (route) => {
    if (new URL(route.request().url()).searchParams.has('cohorts_cursor')) {
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
      await page.unroute('**/alliance/transfers/manage?*');
    } else await route.continue();
  });
  await cohorts.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(cohorts.getByRole('alert')).toBeVisible();
  await cohorts.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(page.locator('article input').first()).toHaveValue('Catalogue 025');
  await cohorts.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(page.locator('article input').first()).toHaveValue('Unsaved existing cohort');
  const assignment = page
    .getByTestId('transfer-choice-picker')
    .filter({ has: page.locator('select[id^="transfer-assignment-"]') });
  await assignment.getByRole('button', { name: 'Search choices', exact: true }).click();
  await expect(assignment).toContainText('55 match');
  await assignment.getByRole('button', { name: 'Next page', exact: true }).click();
  await assignment.getByRole('button', { name: 'Next page', exact: true }).click();
  await assignment.locator('select').selectOption({ label: 'Catalogue 054' });
  await assignment.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(assignment.locator('option:checked')).toHaveText('Catalogue 054');
  await page.getByRole('button', { name: 'Save cohort assignment', exact: true }).click();
  await expect(assignment.locator('option:checked')).toHaveText('Catalogue 054');
  expect(errors).toEqual([]);
});

test('official group membership and archived plan records remain reachable', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`transfer-catalogues-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/alliance/transfers/manage');
  const group = page.getByTestId('transfer-group-kingdoms').first();
  await group.getByRole('button').first().click();
  await expect(group.locator('p').first()).toContainText(
    String(info.project.name === 'desktop' ? 63000 : 63100),
  );
  await group.getByRole('button', { name: 'Next page', exact: true }).click();
  await group.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(group.locator('p').first()).toContainText(
    String(info.project.name === 'desktop' ? 63054 : 63154),
  );
  const plans = page.locator('[data-transfer-catalogue="plans"]');
  await plans.getByRole('button', { name: 'Next page', exact: true }).click();
  await plans.getByRole('button', { name: 'Next page', exact: true }).click();
  await page.getByRole('link', { name: 'Catalogue 054', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Create cohort', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Add Governor', exact: true })).toHaveCount(0);
  expect(errors).toEqual([]);
});
