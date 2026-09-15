import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const territoryVisualFingerprints: Record<string, string> = {
  desktop: '94edd58975746d187b66c135a2917331510f865b3d51cbcaeec19ad9d011c24f',
  mobile: '1ca1593b19f9d7a70f46c81b3243e270ed452e7f1d721efd5a58f7301e984292',
};

const territoryReconciliationFingerprints: Record<string, string> = {
  desktop: 'bed1e04479bd351b4561f561ce5033130ada82fd231d76cbf64d8f8e181add86',
  mobile: 'e35cb779b2eec3bdf92f455d78268847cde23c827c410b3eaa60b0f40fbca383',
};

const territoryVisualTime = new Date('2026-09-14T12:00:00Z');

async function activateVisualGovernor(
  page: Page,
  email = 'territory-visual@example.test',
): Promise<void> {
  await page.clock.setFixedTime(territoryVisualTime);
  await page.goto('/login');
  await page.locator('#email').fill(email);
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

test('Territory Command renders the saved hive without horizontal page overflow', async (
  { page },
  testInfo,
) => {
  await activateVisualGovernor(page);
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Territory Command' })).toBeVisible();
  await expect(page.getByText('Bear Hive Alpha')).toBeVisible();
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  await page.getByRole('link', { name: 'Hive Builder' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Hive Builder' })).toBeVisible();
  await expect(
    page.getByLabel('Interactive Kingdom territory map editor', { exact: true }),
  ).toBeVisible();

  const marchAnalysis = page.getByRole('region', { name: 'Governor march analysis' });
  await expect(marchAnalysis).toBeVisible();
  await expect(marchAnalysis.getByRole('cell', { name: 'North Star' })).toBeVisible();
  await expect(marchAnalysis.getByRole('cell', { name: 'Bear Trap 1' })).toBeVisible();

  const editorOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(editorOverflow).toBeFalsy();

  const expectedFingerprint = territoryVisualFingerprints[testInfo.project.name];
  expect(expectedFingerprint, `Missing Territory visual fingerprint for ${testInfo.project.name}`).toBeDefined();

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('territory-planner.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');

  expect(actualFingerprint).toBe(expectedFingerprint);
});

test('Plan vs observed renders deterministic drift, uncertainty and provenance states', async (
  { page },
  testInfo,
) => {
  await activateVisualGovernor(page, 'territory-reconciliation-visual@example.test');
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await expect(page.getByText('Observed Hive Alpha')).toBeVisible();
  await page.getByRole('link', { name: 'Plan vs observed' }).click();
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Plan vs observed' })).toBeVisible();
  const reconciliationSummary = page.getByRole('region', { name: 'Reconciliation summary' });
  await expect(reconciliationSummary).toBeVisible();
  await expect(reconciliationSummary.getByText('Out of position', { exact: true })).toBeVisible();
  await expect(page.getByText('North Star')).toBeVisible();
  await expect(page.getByText('Unknown Governor')).toBeVisible();
  await expect(page.getByText(/Identity unresolved/)).toBeVisible();
  await expect(page.getByText('Published plan remains unchanged')).toBeVisible();

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  const expectedFingerprint = territoryReconciliationFingerprints[testInfo.project.name];
  expect(
    expectedFingerprint,
    `Missing Territory reconciliation visual fingerprint for ${testInfo.project.name}`,
  ).toBeDefined();

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('territory-reconciliation.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');

  expect(actualFingerprint).toBe(expectedFingerprint);
});
