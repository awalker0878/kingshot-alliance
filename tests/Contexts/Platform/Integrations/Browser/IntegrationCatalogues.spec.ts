import { expect, test } from '@playwright/test';

test('Connections history pages independently, retains drafts and retries an older delivery', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`integration-catalogues-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/confirm-password');
  await page.locator('#confirm-password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('confirm-password'));
  await page.goto('/alliance/integrations');

  const credentials = page.locator('section[aria-labelledby="api-heading"]');
  const webhooks = page.locator('section[aria-labelledby="webhook-heading"]');
  const deliveries = page.locator('section[aria-labelledby="delivery-heading"]');
  const credentialPager = credentials.locator('nav');
  const webhookPager = webhooks.locator('nav');
  const deliveryPager = deliveries.locator('nav');
  await expect(credentialPager).toContainText('61 records');
  await expect(credentials.locator('article').first()).toContainText('Expired');
  await page.locator('#credential-name').fill('Unsaved API draft');
  await page.locator('#webhook-name').fill('Unsaved webhook draft');
  await credentialPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(credentials.locator('article strong').first()).toHaveText('History 035');
  await webhookPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(webhooks.locator('article strong').first()).toHaveText('History 035');
  await expect(credentials.locator('article strong').first()).toHaveText('History 035');

  await page.route('**/alliance/integrations?*', async (route) => {
    if (new URL(route.request().url()).searchParams.has('deliveries_cursor')) {
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
      await page.unroute('**/alliance/integrations?*');
    } else await route.continue();
  });
  await deliveryPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(deliveryPager.getByRole('alert')).toBeVisible();
  await deliveryPager.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(deliveryPager.getByRole('alert')).toHaveCount(0);
  await deliveryPager.getByRole('button', { name: 'Next page', exact: true }).click();
  const rows = deliveries.locator(info.project.name === 'mobile' ? 'article' : 'tbody tr');
  await expect(rows).toHaveCount(11);
  const oldest = rows.last();
  await expect(oldest).toContainText('History 000');
  const previousAttempts =
    info.project.name === 'mobile'
      ? Number((await oldest.innerText()).match(/Attempts: (\d+)/)?.[1])
      : Number(await oldest.locator('td').nth(2).innerText());
  expect(Number.isInteger(previousAttempts)).toBe(true);
  const retried = page.waitForResponse(
    (response) => response.request().method() === 'POST' && response.url().endsWith('/retry'),
  );
  await oldest.getByRole('button', { name: 'Retry delivery', exact: true }).click();
  expect((await retried).status()).toBeLessThan(400);
  // Visual CI uses the synchronous queue. The reserved .test destination is
  // rejected by the real outbound policy before the redirected page reloads.
  await expect(oldest).toContainText(
    'Webhook destination failed the current outbound security policy.',
  );
  if (info.project.name === 'mobile') {
    await expect(oldest).toContainText(`Attempts: ${previousAttempts + 1}`);
  } else {
    await expect(oldest.locator('td').nth(2)).toHaveText(String(previousAttempts + 1));
  }
  await expect(page.locator('#credential-name')).toHaveValue('Unsaved API draft');
  await expect(page.locator('#webhook-name')).toHaveValue('Unsaved webhook draft');
  await credentialPager.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(credentials.locator('article strong').first()).toHaveText('History 060');
  expect(errors).toEqual([]);
});
