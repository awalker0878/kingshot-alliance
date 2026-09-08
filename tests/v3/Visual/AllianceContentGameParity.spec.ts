import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';

const fingerprints: Record<string, Record<string, string>> = {
  rulesPublished: {
    desktop: '35ca3b2c021e1e1b75a2f83bef228c3f2fddc5a77bd4de60c0d8fd3a82546f6b',
    mobile: 'b8faede170ef49bfd498635d0eb8d4aacde51ee7ae0c4a65cda9c767a14711aa',
  },
  rulesEmpty: {
    desktop: '3790abbb5af2620a564138554a9b6e695b72be9c8bee08c16e9ac75e88c98fe8',
    mobile: 'bf448b00738ea968dedfb752860f8960d60f9e2bf686f9942dc6eb55d570e903',
  },
  noticeboard: {
    desktop: '540fa35ea81b2be1f33c413fd1db9947dab62b4c17068fcc8901d021897aab2a',
    mobile: '11a6c16e2b564ab358e42563932440a2b5f68da8a271d3cd71fe8250a3ecd2e1',
  },
  noticeDetail: {
    desktop: '806327edebdf4c29792a35b2f807ce705a7fe641abb9da486959ffc3653776dc',
    mobile: 'c75e0d7e1a32de9d22230daaab609ed9db64f7f23784a607c25194df360d7b8a',
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
