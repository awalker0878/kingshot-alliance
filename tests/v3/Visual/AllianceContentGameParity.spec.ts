import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';

const fingerprints: Record<string, Record<string, string>> = {
  rulesPublished: {
    desktop: '0000000000000000000000000000000000000000000000000000000000000000',
    mobile: '0000000000000000000000000000000000000000000000000000000000000000',
  },
  rulesEmpty: {
    desktop: '0000000000000000000000000000000000000000000000000000000000000000',
    mobile: '0000000000000000000000000000000000000000000000000000000000000000',
  },
  noticeboard: {
    desktop: 'fccbadf99ba56470d0cbecaa51c0e82756e53146c6b1cd6aa39e4fb1ea1ff348',
    mobile: '44084d405757bed544f009242f2abf8847745ee77dd418760a267a89b6ba7949',
  },
  noticeDetail: {
    desktop: '703a95336fc6895c48863110e7fe72453ae122cf7468d70777831ecd4b4fb08d',
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

  await expect(
    page.getByRole('heading', { name: 'Alliance Rules', level: 1, exact: true }),
  ).toBeVisible();
  await expect(page.getByText('Join Bear Hunt rallies on time.')).toBeVisible();
  await expect(
    page.getByRole('heading', { name: 'Alliance Rules', level: 2, exact: true }),
  ).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Edit Alliance Rules', level: 2 })).toBeVisible();
  const rules = page.getByLabel('Rules');
  await expect(rules).toHaveValue(/Follow R4\/R5 battle calls/);
  await expect(rules).toHaveAttribute('maxlength', '10000');
  await expect(page.getByRole('button', { name: 'Save Alliance Rules' })).toBeEnabled();

  await assertVisual(page, testInfo, 'rulesPublished');
});

test('Alliance Rules expose the localized empty and editable state', async ({ page }, testInfo) => {
  await login(page, 'content-empty-visual@example.test');
  await page.goto('/alliance/rules');
  await page.waitForLoadState('networkidle');

  await expect(
    page.getByRole('heading', { name: 'Alliance Rules', level: 1, exact: true }),
  ).toBeVisible();
  await expect(page.getByText('No Alliance Rules have been posted yet.')).toBeVisible();
  await expect(
    page.getByRole('heading', { name: 'Alliance Rules', level: 2, exact: true }),
  ).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Add Alliance Rules', level: 2 })).toBeVisible();
  await expect(page.getByLabel('Rules')).toHaveValue('');
  await expect(page.getByRole('button', { name: 'Save Alliance Rules' })).toBeEnabled();

  await assertVisual(page, testInfo, 'rulesEmpty');
});

test('Alliance Notice cards expose lightweight reactions without ranking UI', async ({ page }, testInfo) => {
  await login(page);
  await page.goto('/alliance/content');
  await page.waitForLoadState('networkidle');

  const notice = page.locator('article').filter({
    has: page.getByRole('heading', { name: 'Bear Hunt Rally Window', level: 3 }),
  });
  await expect(notice).toBeVisible();

  const like = notice.getByRole('button', {
    name: /Remove your Like from this Alliance Notice\. 1 likes\./,
  });
  const dislike = notice.getByRole('button', {
    name: /Dislike this Alliance Notice\. 1 dislikes\./,
  });
  await expect(like).toHaveAttribute('aria-pressed', 'true');
  await expect(dislike).toHaveAttribute('aria-pressed', 'false');
  await expect(page.getByText(/trending|popular|score|approval ratio/i)).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'Alliance Rules' }).first()).toBeVisible();

  await assertVisual(page, testInfo, 'noticeboard');
});

test('Alliance Notice detail preserves reaction state and anti-ranking semantics', async ({ page }, testInfo) => {
  await login(page);
  await page.goto('/alliance/content/bear-hunt-rally-window');
  await page.waitForLoadState('networkidle');

  const article = page.getByRole('article');
  await expect(
    article.getByRole('heading', { name: 'Bear Hunt Rally Window', level: 1 }),
  ).toBeVisible();
  const like = article.getByRole('button', {
    name: /Remove your Like from this Alliance Notice\. 1 likes\./,
  });
  const dislike = article.getByRole('button', {
    name: /Dislike this Alliance Notice\. 1 dislikes\./,
  });
  await expect(like).toHaveAttribute('aria-pressed', 'true');
  await expect(dislike).toHaveAttribute('aria-pressed', 'false');
  await expect(page.getByText(/trending|popular|score|approval ratio/i)).toHaveCount(0);

  await assertVisual(page, testInfo, 'noticeDetail');
});
