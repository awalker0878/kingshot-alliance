import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const factualProgressionVisualFingerprints: Record<
  string,
  { library: string; governor: string }
> = {
  desktop: {
    library: 'f3a8c27a39e1c641e6cb570cc82f86d2b26fd08a33f5227a0d6c2b386be9573d',
    governor: '570917ef26d8f21efcbd219f57236df271682f5a49bd2df96ee736bf5c60cd83',
  },
  mobile: {
    library: 'c549584809471cfad5b8cb751be4d30c06518149d9846d0700f2cf1979de39e1',
    governor: 'ebda8baf3fec8fa73c099f4dab29dc10a95134d9dd707254b7f427a3ce5e7f5b',
  },
};

async function loginAndActivateGovernor(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('ux-p9-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');

  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  await expect(identitySwitcher).toBeVisible();
  const label = (await identitySwitcher.textContent()) ?? '';
  if (/select governor/i.test(label)) {
    await identitySwitcher.click();
    const listbox = page.getByRole('listbox', { name: 'Active Governor' });
    await listbox.getByRole('option', { name: /Lady Seraphina/ }).click();
    await page.waitForURL('**/dashboard');
  }
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
}

async function fingerprint(surface: Locator): Promise<string> {
  const screenshot = await surface.screenshot({
    animations: 'disabled',
    caret: 'hide',
    scale: 'css',
  });

  return createHash('sha256').update(screenshot).digest('hex');
}

test('Factual Progression library, Governor view, and Goal Planner remain complete on desktop and mobile', async (
  { page },
  testInfo,
) => {
  await loginAndActivateGovernor(page);

  await page.goto('/progression?family=academy_research&family_q=Fortified%20Mail%20VI');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Factual Progression', level: 1 })).toBeVisible();
  await expect(page.getByText('2026.08.23.2', { exact: true }).first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Fortified Mail VI', exact: true })).toBeVisible();
  await expect(page.getByText('Source gaps', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Sources & evidence', { exact: true }).first()).toBeVisible();
  await expectNoHorizontalOverflow(page);

  const expected = factualProgressionVisualFingerprints[testInfo.project.name];
  expect.soft(
    await fingerprint(page.locator('main')),
    `Update Factual Progression library fingerprint for ${testInfo.project.name}`,
  ).toBe(expected.library);

  await page.goto('/progression/governor');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Lady Seraphina', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Observation history' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Saved loadouts' })).toBeVisible();
  await expect(
    page.getByText(/Governor observations are unavailable in the current Alliance context/),
  ).toBeVisible();
  await expectNoHorizontalOverflow(page);

  expect.soft(
    await fingerprint(page.locator('main')),
    `Update Governor progression fingerprint for ${testInfo.project.name}`,
  ).toBe(expected.governor);

  await page.goto('/progression/governor/planner?family=governor_gear&subject=hood&target=step%3A3');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Progression Goal Planner', level: 1 })).toBeVisible();
  await expect(page.getByTestId('planner-current-state')).toContainText('Current state is unknown');
  await expect(page.getByTestId('planner-target-state')).toContainText('Green ★3');
  await expect(page.getByTestId('planner-calculator-gate')).toContainText('Calculator ready');
  await expect(page.getByTestId('planner-calculate')).toHaveCount(0);
  await expectNoHorizontalOverflow(page);
});
