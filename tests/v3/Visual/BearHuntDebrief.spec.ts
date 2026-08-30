import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const bearHuntDebriefVisualFingerprints: Record<string, string> = {
  desktop: 'd61505159977835faf7a8425eae505750497c438b4bb0a98f3aa9246bd64b9a4',
  mobile: 'a5435dfe7a7ac9d8829d7ffa2ac6604b429f1757c45171ea5f180cb99b66ca7e',
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

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
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

  for (const governor of [
    'Bear Marshal',
    'Ember Scout',
    'Frost Guard of the Northern Aurora Vanguard Expedition',
    'Unknown Ember',
  ]) {
    await expect(
      page.locator('h3:visible, td:visible, strong:visible', { hasText: governor }).first(),
    ).toBeVisible();
  }

  await expectNoHorizontalOverflow(page);

  // Capture only the capability surface. The application shell includes fixed
  // mobile navigation that Playwright composites at different scroll offsets
  // during full-page capture, which makes otherwise-identical pixels unstable.
  // Shell responsiveness is still exercised above through real-page overflow
  // assertions and by the repository's dedicated application-shell visual tests.
  const screenshot = await page.locator('main').screenshot({
    animations: 'disabled',
    caret: 'hide',
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

test('Bear Hunt Debrief long localized strings do not overflow', async ({ page }) => {
  await openBearHuntDebrief(page);
  await page.evaluate(() => window.localStorage.setItem('kingshot.locale', 'de'));
  await page.reload();
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.locator('html')).toHaveAttribute('lang', 'de');
  await expect(page.getByRole('heading', { name: 'Bärenjagd-Auswertung', level: 1 })).toBeVisible();
  await expect(
    page.getByText(
      'Prüfe Schaden, Anwesenheit, Rally-Teilnahme, offene Gouverneure und letzte Jagden.',
    ),
  ).toBeVisible();
  await expectNoHorizontalOverflow(page);
});
