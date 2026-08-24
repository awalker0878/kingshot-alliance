import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';

const fingerprints: Record<string, Record<string, string>> = {
  rulesPublished: {
    desktop: '27af53dbdb923889fccba24a435a09bdd9573fefb99ed4faa0c6c77a9fd54ddb',
    mobile: '56982afc2084d5d96f9b6613ab0f4456afccfcf0a245c0140a299423fdcd5619',
  },
  rulesEmpty: {
    desktop: '6e17843af5f8f5e8c11f37fe102858e39d5eb23975968176148f3c035fa29f89',
    mobile: 'f22cc8b63006d4755667ecc0fa2a5b9481ff34871ebb9bbb7a6accd2a0e6782e',
  },
  noticeboard: {
    desktop: '15e66fa7d126582a61bac9fc07de2d4021bac0bb421ec0236560fa0d1dca7145',
    mobile: '44084d405757bed544f009242f2abf8847745ee77dd418760a267a89b6ba7949',
  },
  noticeDetail: {
    desktop: '63721aafbe9c3e4db251e2cad05c31faee8cc954a2fd81467df3d152259be08e',
    mobile: '0f6dd10b829b35f2924845b391c22e7be4646f8479ef035fa802f789332008b3',
  },
};

type VisualSurface = 'rulesPublished' | 'rulesEmpty' | 'noticeboard' | 'noticeDetail';

test.beforeAll(() => {
  execFileSync(
    'php',
    [
      'artisan',
      'tinker',
      '--execute=\\Tests\\v3\\Fixtures\\AllianceContentGameParityVisualFixture::seed();',
    ],
    { stdio: 'inherit' },
  );
});

async function login(page: Page, email = 'content-visual@example.test'): Promise<void> {
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
      await page
        .getByRole('listbox', { name: 'Active Governor' })
        .getByRole('option')
        .first()
        .click();
      await page.waitForURL('**/dashboard');
    }
  }
}

async function assertVisual(
  page: Page,
  testInfo: TestInfo,
  surface: VisualSurface,
): Promise<void> {
  await page.evaluate(() => document.fonts.ready);
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath(`alliance-content-${surface}.png`),
    scale: 'css',
  });
  const actual = createHash('sha256').update(screenshot).digest('hex');
  const expected = fingerprints[surface]?.[testInfo.project.name];

  expect(actual, `Update ${surface} visual fingerprint for ${testInfo.project.name}`).toBe(
    expected,
  );
}

test('Alliance Rules are a first-class readable and editable member surface', async ({ page }, testInfo) => {
  await login(page);
  await page.goto('/alliance/rules');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Alliance Rules', level: 1 })).toBeVisible();
  await expect(page.getByText('Join Bear Hunt rallies on time.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Update rules' })).toBeVisible();
  await assertVisual(page, testInfo, 'rulesPublished');
});

test('Alliance Rules expose the localized empty and editable state', async ({ page }, testInfo) => {
  await login(page, 'content-empty-visual@example.test');
  await page.goto('/alliance/rules');
  await page.waitForLoadState('networkidle');
  await expect(page.getByText('No Alliance Rules have been published yet.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Publish rules' })).toBeVisible();
  await assertVisual(page, testInfo, 'rulesEmpty');
});

test('Alliance Notice cards expose lightweight reactions without ranking UI', async ({ page }, testInfo) => {
  await login(page);
  await page.goto('/alliance/content');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Noticeboard', level: 1 })).toBeVisible();
  await expect(page.getByText('Bear Hunt Rally Timing')).toBeVisible();
  await expect(page.getByRole('button', { name: /Acknowledge/i }).first()).toBeVisible();
  await expect(page.getByText(/leaderboard/i)).toHaveCount(0);
  await assertVisual(page, testInfo, 'noticeboard');
});

test('Alliance Notice detail preserves reaction state and anti-ranking semantics', async ({ page }, testInfo) => {
  await login(page);
  await page.goto('/alliance/content');
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: 'Bear Hunt Rally Timing' }).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Bear Hunt Rally Timing', level: 1 })).toBeVisible();
  await expect(page.getByRole('button', { name: /Acknowledge/i })).toBeVisible();
  await expect(page.getByText(/leaderboard/i)).toHaveCount(0);
  await assertVisual(page, testInfo, 'noticeDetail');
});
