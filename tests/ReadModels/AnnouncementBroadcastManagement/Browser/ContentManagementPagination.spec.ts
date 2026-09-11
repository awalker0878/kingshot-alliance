import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function openManager(page: Page): Promise<void> {
  execFileSync(
    'php',
    [
      'artisan',
      'tinker',
      '--execute=\\Tests\\ReadModels\\AnnouncementBroadcastManagement\\Fixtures\\ContentManagementPaginationFixture::seed();',
    ],
    { stdio: 'inherit' },
  );
  await page.goto('/login');
  await page.locator('#email').fill('content-manager-pages@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  const response = await page.goto('/alliance/content/manage');
  expect(response?.status()).toBe(200);
}

test('manager pages preserve drafts and selected options outside the current catalogue page', async ({
  page,
}) => {
  await openManager(page);
  const rows = page.getByTestId('content-catalogue-row');
  await expect(rows).toHaveCount(20);
  const current = rows.filter({
    has: page.getByRole('heading', { name: 'Manager page 39', exact: true }),
  });
  await current.getByRole('button', { name: 'Edit content', exact: true }).click();
  await page.getByLabel('Title', { exact: true }).fill('Unsaved page-spanning title');
  const category = page.locator('#content-category');
  await expect(category.locator('option:checked')).toHaveText('Manager category 0');
  const selectedId = await category.inputValue();
  await page
    .locator('[data-collection="content"]')
    .getByRole('button', { name: 'Next page' })
    .click();
  await expect(
    rows.filter({ has: page.getByRole('heading', { name: 'Manager page 19', exact: true }) }),
  ).toBeVisible();
  await expect(rows).toHaveCount(20);
  await expect(page.getByLabel('Title', { exact: true })).toHaveValue(
    'Unsaved page-spanning title',
  );
  await expect(category).toHaveValue(selectedId);
  await expect(category.locator('option:checked')).toHaveText('Manager category 0');
  await page
    .locator('[data-collection="content"]')
    .getByRole('link', { name: 'First page' })
    .click();
  await expect(current).toBeVisible();
  await expect(page.getByLabel('Title', { exact: true })).toHaveValue(
    'Unsaved page-spanning title',
  );
  await page.locator('#content-category-search').fill('Manager category 30');
  await page.locator('#content-category-search').press('Enter');
  await expect(category.locator('option')).toHaveCount(3); // empty, explicit current selection, matching row
  await expect(category).toHaveValue(selectedId);
  await expect(category.locator('option:checked')).toHaveText('Manager category 0');
});

test('lazy history failures are recoverable and every retained run and revision remains reachable', async ({
  page,
}) => {
  await openManager(page);
  const item = page
    .getByTestId('content-catalogue-row')
    .filter({ has: page.getByRole('heading', { name: 'Manager page 39', exact: true }) });
  const history = item
    .locator('details')
    .filter({ has: page.locator('summary').filter({ hasText: 'Delivery history' }) });
  await page.route(
    '**/alliance/content/manage/*/runs?*',
    (route) =>
      route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: '{"message":"Temporary fixture failure"}',
      }),
    { times: 1 },
  );
  await history.locator('summary').click();
  await expect(history.getByRole('alert')).toBeVisible();
  await expect(history.locator('[data-broadcast-run-id]')).toHaveCount(0);
  await history.getByRole('button', { name: 'First page' }).click();
  await expect(history.locator('[data-broadcast-run-id]')).toHaveCount(5);
  const ids = await history
    .locator('[data-broadcast-run-id]')
    .evaluateAll((rows) => rows.map((row) => row.getAttribute('data-broadcast-run-id')));
  await history.getByRole('button', { name: 'Next page' }).click();
  await expect(history.locator('[data-broadcast-run-id]').first()).not.toHaveAttribute(
    'data-broadcast-run-id',
    ids[0]!,
  );
  ids.push(
    ...(await history
      .locator('[data-broadcast-run-id]')
      .evaluateAll((rows) => rows.map((row) => row.getAttribute('data-broadcast-run-id')))),
  );
  await history.getByRole('button', { name: 'Next page' }).click();
  await expect(history.locator('[data-broadcast-run-id]')).toHaveCount(2);
  ids.push(
    ...(await history
      .locator('[data-broadcast-run-id]')
      .evaluateAll((rows) => rows.map((row) => row.getAttribute('data-broadcast-run-id')))),
  );
  expect(ids).toHaveLength(12);
  expect(new Set(ids).size).toBe(12);
  await expect(history.getByRole('button', { name: 'Next page' })).toHaveCount(0);
  const revisions = item
    .locator('details')
    .filter({ has: page.locator('summary').filter({ hasText: 'Revisions' }) });
  await revisions.locator('summary').click();
  await expect(revisions.getByRole('button', { name: /^Restore revision/ })).toHaveCount(10);
  await revisions.getByRole('button', { name: 'Next page' }).click();
  await expect(revisions.getByRole('button', { name: /^Restore revision/ })).toHaveCount(2);
  await expect(revisions.getByRole('button', { name: 'Next page' })).toHaveCount(0);
});
