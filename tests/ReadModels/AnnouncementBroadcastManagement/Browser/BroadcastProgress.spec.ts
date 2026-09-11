import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function fixture(method: 'seed' | 'finish'): void {
  execFileSync(
    'php',
    [
      'artisan',
      'tinker',
      `--execute=\\Tests\\ReadModels\\AnnouncementBroadcastManagement\\Fixtures\\AnnouncementBroadcastTraversalFixture::${method}();`,
    ],
    { stdio: 'inherit' },
  );
}

test('announcement queue progress distinguishes pending work from completed recipients', async ({
  page,
}) => {
  fixture('seed');
  await page.goto('/login');
  await page.locator('#email').fill('broadcast-progress-0@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  const response = await page.goto('/alliance/content/manage');
  expect(response?.status()).toBe(200);
  const item = page
    .locator('article')
    .filter({
      has: page.getByRole('heading', { name: 'Resumable broadcast fixture', exact: true }),
    });
  await expect(item).toBeVisible();
  await item.locator('summary').filter({ hasText: 'Delivery history' }).click();
  await expect(item.locator('[data-broadcast-status="pending"]')).toHaveText(
    'Preparing recipients — more work remains',
  );
  await expect(
    item.getByText(
      '1 examined · 0 no longer eligible · 0 without enabled routes · 0 already queued',
    ),
  ).toBeVisible();
  await expect(item.getByText('Recipient processing complete', { exact: true })).toHaveCount(0);
  fixture('finish');
  await page.reload();
  await item.locator('summary').filter({ hasText: 'Delivery history' }).click();
  await expect(item.locator('[data-broadcast-status="queued"]')).toHaveText(
    'Recipient processing complete',
  );
  await expect(
    item.getByText(
      '3 examined · 0 no longer eligible · 0 without enabled routes · 0 already queued',
    ),
  ).toBeVisible();
  await expect(
    item.getByText('3 recipients · 3 sent · 0 queued · 0 failed · 0 read'),
  ).toBeVisible();
  await expect(item.locator('[data-broadcast-status="pending"]')).toHaveCount(0);
});
