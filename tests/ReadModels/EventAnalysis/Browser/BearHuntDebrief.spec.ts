import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const bearHuntDebriefVisualFingerprints: Record<string, string> = {
  desktop: 'd61505159977835faf7a8425eae505750497c438b4bb0a98f3aa9246bd64b9a4',
  mobile: 'a5435dfe7a7ac9d8829d7ffa2ac6604b429f1757c45171ea5f180cb99b66ca7e',
};

async function openBearHuntDebrief(
  page: Page,
  eventName = 'Bear Hunt · Debrief Visual',
  completed = true,
): Promise<void> {
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
      await page
        .getByRole('listbox', { name: 'Active Governor' })
        .getByRole('option')
        .first()
        .click();
      await page.waitForURL('**/dashboard');
    }
  }

  await page.goto('/events');
  await page.getByRole('link', { name: eventName }).first().click();
  await page.getByRole('link', { name: 'Bear Hunt Debrief' }).click();
  await page.waitForLoadState('networkidle');

  if (!completed) return;

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

test('Bear Hunt Debrief remains readable and complete on desktop and mobile', async ({
  page,
}, testInfo) => {
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

test('Bear Hunt complete Governor pages retain exact totals and independent personal results through retry', async ({
  page,
}) => {
  await openBearHuntDebrief(page, 'Bear Hunt · Catalogue Visual', false);
  const leaderboard = page.locator('section[aria-labelledby="leaderboard-heading"]');
  const pager = leaderboard.locator('[data-event-catalogue="governor"]');
  const visibleRows = leaderboard.locator('table:visible tbody tr, article:visible');
  const personal = page.locator('section[aria-labelledby="your-hunt-heading"]');
  const total = new Intl.NumberFormat('en').format(9223372036854775807n * 61n);
  const score = new Intl.NumberFormat('en').format(9223372036854775807n);
  await expect(pager.getByText('61 records')).toBeVisible();
  await expect(visibleRows).toHaveCount(25);
  await expect(page.getByText(total, { exact: true }).first()).toBeVisible();
  await expect(personal.getByText(score, { exact: true })).toBeVisible();
  await expect(personal.getByText('#61', { exact: true })).toBeVisible();
  let inject = true;
  await page.route('**/events/*/debrief?**', async (route) => {
    if (inject && route.request().url().includes('governor_cursor=')) {
      inject = false;
      await route.abort('failed');
    } else await route.continue();
  });
  await pager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(pager.getByRole('alert')).toContainText('This page could not be loaded');
  await expect(visibleRows).toHaveCount(25);
  await pager.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(
    leaderboard.getByText('History Debrief Governor 25', { exact: true }).filter({ visible: true }),
  ).toBeVisible();
  await expect(personal.getByText('#61', { exact: true })).toBeVisible();
  await expect(page.getByText(total, { exact: true }).first()).toBeVisible();
  await pager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(visibleRows).toHaveCount(11);
  await expect(
    leaderboard.getByText('Bear Marshal', { exact: true }).filter({ visible: true }),
  ).toBeVisible();
  await pager.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(
    leaderboard.getByText('History Debrief Governor 0', { exact: true }).filter({ visible: true }),
  ).toBeVisible();
  await expect(personal.getByText('#61', { exact: true })).toBeVisible();
  await expectNoHorizontalOverflow(page);
});
