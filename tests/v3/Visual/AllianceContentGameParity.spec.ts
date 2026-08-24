import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';

const fingerprints: Record<string, Record<string, string>> = {
  rulesPublished: {
    desktop: '31f384067dd51190fb83d6ee6aa0430f42f9830eb018081b18af58250e555262',
    mobile: '56982afc2084d5d96f9b6613ab0f4456afccfcf0a245c0140a299423fdcd5619',
  },
  rulesEmpty: {
    desktop: 'cb6adfd23c2af9f52e238eb9dd61183224112b53cfd032c76ca8a6816bd07c34',
    mobile: 'f22cc8b63006d4755667ecc0fa2a5b9481ff34871ebb9bbb7a6accd2a0e6782e',
  },
  noticeboard: {
    desktop: '18c5db65b893d3e163dc3628fb4a05472d9ac8806a04fc70467847b44e87a2be',
    mobile: '44084d405757bed544f009242f2abf8847745ee77dd418760a267a89b6ba7949',
  },
  noticeDetail: {
    desktop: '8fb3f7c7cb4ba3c780caf704bf63ed1f1c36f868c63d56e7d93d61f9db13d7cd',
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
  const rules = page.getByRole('textbox', { name: 'Rules', exact: true });
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
  await expect(page.getByRole('textbox', { name: 'Rules', exact: true })).toHaveValue('');
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
