import { readFile } from 'node:fs/promises';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const t15Time = new Date('2026-09-14T12:00:00Z');

async function login(page: Page): Promise<void> {
  await page.clock.setFixedTime(t15Time);
  await page.goto('/login');
  await page.locator('#email').fill('territory-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');

  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (await identitySwitcher.isVisible()) {
    const label = (await identitySwitcher.textContent()) ?? '';
    if (/select governor/i.test(label)) {
      await identitySwitcher.click();
      await page.getByRole('listbox', { name: 'Active Governor' }).getByRole('option').first().click();
      await page.waitForURL('**/dashboard');
    }
  }
}

async function openPlan(page: Page): Promise<string> {
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await expect(page.getByText('Bear Hive Alpha')).toBeVisible();
  await page.getByRole('link', { name: 'Hive Builder' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Hive Builder' })).toBeVisible();
  const match = new URL(page.url()).pathname.match(/^\/territory\/([0-9A-HJKMNP-TV-Z]{26})$/i);
  expect(match, 'The editor URL must expose the immutable plan ULID.').not.toBeNull();

  return match![1];
}

test('@t15 Explorer to share/revoke journey requires real artwork evidence', async ({ page }) => {
  test.skip(
    process.env.KINGSHOT_T15_ARTWORK_READY !== '1',
    'T15 is intentionally unverified until the authorized Kingshot master artwork pack is delivered and the strict artwork gate passes.',
  );

  await login(page);

  // Explorer: prove the source/provenance surface opens before entering a plan.
  await page.goto('/territory/explore');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: /Kingdom Explorer/i })).toBeVisible();
  await expect(page.getByText(/confidence/i).first()).toBeVisible();

  // Planning and assignment.
  const planId = await openPlan(page);
  const assignment = page.getByLabel('Governor assignment').first();
  await expect(assignment).toBeVisible();
  await assignment.selectOption({ label: 'Map Warden' });
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText(/Revision \d+/).first()).toBeVisible();

  // Reconciliation uses the same persisted plan.
  await page.goto(`/territory/${planId}/reconciliation`);
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Plan vs observed' })).toBeVisible();

  // Review and publication return to the editor and create an immutable revision.
  await page.goto(`/territory/${planId}`);
  await page.waitForLoadState('networkidle');
  const review = page.getByRole('group', { name: 'Plan review' });
  await expect(review).toBeVisible();
  await review.getByRole('combobox').selectOption('approved');
  await review.getByRole('textbox').fill('T15 release-candidate review.');
  await review.getByRole('button', { name: 'Record review' }).click();
  await expect(review.getByText('Approved', { exact: true }).last()).toBeVisible();

  await page.getByRole('button', { name: 'Publish', exact: true }).click();
  await expect(page.getByText('Published', { exact: true }).first()).toBeVisible();

  // Artwork-bearing export: successful download is not enough. The SVG must embed raster art
  // and record a non-unavailable artwork version in its reproducibility footer.
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Export SVG' }).click();
  const download = await downloadPromise;
  const downloadPath = await download.path();
  expect(downloadPath).not.toBeNull();
  const svg = await readFile(downloadPath!, 'utf8');
  expect(svg).toContain('<image id="territory-art-0"');
  expect(svg).toMatch(/Artwork (?!unavailable)[^<]+/);

  // Reopen the published plan from the plan list before sharing it.
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: 'Hive Builder' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(new RegExp(`/territory/${planId}$`));
  await expect(page.getByText('Published', { exact: true }).first()).toBeVisible();

  // Private sharing is scoped to the immutable published revision and then revoked.
  const sharing = page.getByRole('group', { name: 'Private sharing' });
  await expect(sharing).toBeVisible();
  const selects = sharing.getByRole('combobox');
  await selects.nth(0).selectOption({ index: 1 });
  await selects.nth(1).selectOption({ label: 'North Reviewer' });
  await sharing.getByRole('checkbox').first().check();
  await sharing.getByRole('button', { name: 'Create private share' }).click();
  await expect(sharing.getByText(/shown once/i)).toBeVisible();
  await expect(sharing.locator('code')).not.toHaveText('');

  const activeShare = sharing.locator('li').filter({ has: sharing.getByRole('button', { name: 'Revoke' }) }).first();
  await expect(activeShare).toBeVisible();
  await activeShare.getByRole('button', { name: 'Revoke' }).click();
  await expect(activeShare.getByRole('button', { name: 'Revoke' })).toHaveCount(0);
});
