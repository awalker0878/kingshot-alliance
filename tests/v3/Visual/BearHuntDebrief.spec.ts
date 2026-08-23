import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const bearHuntDebriefVisualFingerprints: Record<string, string> = {
  desktop: 'pending-desktop-bear-hunt-debrief',
  mobile: 'pending-mobile-bear-hunt-debrief',
};

async function openBearHuntDebrief(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('bear-debrief-visual@example.test');
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

  await page.goto('/events');
  await page.getByRole('link', { name: /Bear Hunt · Debrief Visual/ }).first().click();
  await page.getByRole('link', { name: 'Bear Hunt Debrief' }).click();
  await page.waitForLoadState('networkidle');

  // Bear Hunt is recurring, so the Event page opens the next scheduled occurrence.
  // Navigate through the Debrief's own run history to the deterministic completed
  // fixture run that contains Results, Attendance, Rally and unresolved Evidence.
  await page.getByRole('link', { name: /Aug 23/ }).click();
  await page.waitForLoadState('networkidle');
}

test('Bear Hunt Debrief remains readable and complete on desktop and mobile', async (
  { page },
  testInfo,
) => {
  await openBearHuntDebrief(page);
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Bear Hunt Debrief', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Your Hunt' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Governor leaderboard' })).toBeVisible();
  await expect(page.getByRole('heading', { name: /Governors need matching/ })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Recent Bear Hunt trends' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Bear Hunt run history' })).toBeVisible();

  for (const governor of ['Bear Marshal', 'Ember Scout', 'Unknown Ember']) {
    await expect(
      page.locator('h3:visible, strong:visible', { hasText: governor }).first(),
    ).toBeVisible();
  }

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('bear-hunt-debrief.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');
  const expectedFingerprint = bearHuntDebriefVisualFingerprints[testInfo.project.name];

  expect(
    actualFingerprint,
    `Update Bear Hunt Debrief visual fingerprint for ${testInfo.project.name}`,
  ).toBe(expectedFingerprint);
});
