import { expect, test } from '@playwright/test';

test('Platform pages retain selected settings and recover older exhausted work', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`platform-catalogues-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/confirm-password');
  await page.locator('#confirm-password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('confirm-password'));
  await page.goto('/platform');
  const fleet = page.locator('section[aria-labelledby="alliances-heading"]');
  const rows = fleet.locator(info.project.name === 'mobile' ? 'article' : 'tbody tr');
  await expect(rows.first()).toContainText('Platform history 060');
  await rows.first().getByRole('link', { name: 'Manage', exact: true }).click();
  const selected = page.locator('section[aria-labelledby="selected-heading"]');
  await expect(selected).toContainText('Manage Platform history 060');
  await page.locator('#platform-admin-email').fill('unsaved-platform@example.test');
  await page.locator('#platform-lifecycle-reason').fill('Unsaved lifecycle reason');
  await page.locator('#hold-reason').fill('Unsaved record hold');
  const featurePager = page.locator('[data-platform-catalogue="features"]');
  const outboxPager = page.locator('[data-platform-catalogue="outboxFailures"]');
  await expect(featurePager).toContainText('61 records');
  await fleet.locator('nav').getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(rows.first()).toContainText('Platform history 035');
  await expect(selected).toContainText('Manage Platform history 060');
  await featurePager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(selected.locator('li').first()).toContainText('Platform history 035');
  await expect(rows.first()).toContainText('Platform history 035');

  await page.route('**/platform?*', async (route) => {
    if (new URL(route.request().url()).searchParams.has('outboxFailures_cursor')) {
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
      await page.unroute('**/platform?*');
    } else await route.continue();
  });
  await outboxPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(outboxPager.getByRole('alert')).toBeVisible();
  await outboxPager.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(outboxPager.getByRole('alert')).toHaveCount(0);
  await outboxPager.getByRole('button', { name: 'Next page', exact: true }).click();
  const outbox = outboxPager.locator('..');
  const event = info.project.name === 'mobile' ? 'Platform history 001' : 'Platform history 000';
  const title = outbox.getByText(event, { exact: true });
  await expect(title).toBeVisible();
  const failure = title.locator('..').locator('..').locator('..');
  await failure.getByRole('button', { name: 'Release for retry', exact: true }).click();
  const dialog = page.getByRole('dialog');
  const retried = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      response.url().includes('/platform/operations/outbox/') &&
      response.url().endsWith('/retry'),
  );
  await dialog.getByRole('button', { name: 'Release for retry', exact: true }).click();
  expect((await retried).status()).toBeLessThan(400);
  await expect(title).toHaveCount(0);
  await expect(page.locator('#platform-admin-email')).toHaveValue('unsaved-platform@example.test');
  await expect(page.locator('#platform-lifecycle-reason')).toHaveValue('Unsaved lifecycle reason');
  await expect(page.locator('#hold-reason')).toHaveValue('Unsaved record hold');
  await fleet.locator('nav').getByRole('button', { name: 'First page', exact: true }).click();
  await expect(rows.first()).toContainText('Platform history 060');
  expect(errors).toEqual([]);
});
