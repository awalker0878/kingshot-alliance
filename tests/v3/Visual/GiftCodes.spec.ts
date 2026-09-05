import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function loginWithGovernor(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('gift-code-catalogue-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');

  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (/select governor/i.test((await identitySwitcher.textContent()) ?? '')) {
    await identitySwitcher.click();
    await page
      .getByRole('listbox', { name: 'Active Governor' })
      .getByRole('option')
      .first()
      .click();
    await page.waitForURL('**/dashboard');
  }
}

async function loginForModeration(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('gift-code-moderation-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
}

test('Gift Code catalogue and guided Governor handoff work without desktop or mobile overflow', async ({
  page,
}, testInfo) => {
  await loginWithGovernor(page);
  await page.goto('/gift-codes');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Gift Codes', level: 1 })).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Gift Code catalogue views' })).toBeVisible();
  const code = `VISUAL-GIFT-${testInfo.project.name.toUpperCase()}`;
  await page.getByRole('link', { name: code, exact: true }).click();
  await page.waitForLoadState('networkidle');

  const detail = page.locator('section[aria-labelledby="gift-code-detail"]');
  await expect(detail.getByRole('heading', { name: code })).toBeVisible();
  const governorChoices = detail.locator('input[type="checkbox"]');
  await expect(governorChoices).toHaveCount(2);
  await governorChoices.nth(0).check();
  await governorChoices.nth(1).check();
  await detail.getByRole('button', { name: 'Prepare 2 selected Governors' }).click();
  await page.waitForLoadState('networkidle');

  await expect(detail.getByText('Governor 1 of 2')).toBeVisible();
  await expect(detail.getByRole('button', { name: 'Copy Player ID' })).toBeVisible();
  await expect(detail.getByRole('button', { name: 'Copy code' })).toBeVisible();
  await expect(detail.getByRole('link', { name: 'Open official Gift Code Center' })).toHaveAttribute(
    'href',
    /ks-giftcode\.centurygame\.com/,
  );

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  const unnamedControls = await page
    .locator('main button, main input, main select, main textarea, main a[href]')
    .evaluateAll(
      (controls) =>
        controls.filter((control) => {
          const element = control as HTMLElement;
          if (element.offsetParent === null || element.getAttribute('aria-hidden') === 'true') {
            return false;
          }
          const id = element.getAttribute('id');
          const explicitLabel = id
            ? document.querySelector(`label[for="${CSS.escape(id)}"]`)
            : element.closest('label');

          return !(
            element.getAttribute('aria-label') ||
            element.getAttribute('aria-labelledby') ||
            element.getAttribute('title') ||
            element.textContent?.trim() ||
            explicitLabel
          );
        }).length,
    );
  expect(unnamedControls, 'Every visible Gift Code control needs an accessible name').toBe(0);
});

test('Gift Code redemption workspace creates, resumes, skips and records a multi-Governor run', async ({
  page,
}) => {
  await loginWithGovernor(page);
  await page.goto('/gift-codes/workspace');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Gift Code Workspace', level: 1 })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Redeem all ready' })).toBeVisible();
  await page.getByRole('button', { name: 'Redeem all ready' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.getByText('Current redemption run')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Prepare official handoff' })).toBeVisible();
  const sessionUrl = page.url();
  expect(sessionUrl).toContain('session=');

  await page.reload();
  await page.waitForLoadState('networkidle');
  await expect(page.getByText('Current redemption run')).toBeVisible();

  await page.getByRole('button', { name: 'Skip for now' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByText(/1 skipped/i)).toBeVisible();

  const prepare = page.getByRole('button', { name: 'Prepare official handoff' });
  await expect(prepare).toBeVisible();
  await prepare.click();
  await page.waitForLoadState('networkidle');
  await page.getByLabel('Observed official-center outcome').selectOption('redeemed');
  await page.getByRole('button', { name: 'Record and continue' }).click();
  await page.waitForLoadState('networkidle');

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
});

test('Gift Code moderation exposes governed queues and installed source adapters', async ({ page }) => {
  await loginForModeration(page);
  await page.goto('/platform/gift-codes');
  if (/confirm-password/.test(page.url())) {
    await page.locator('#password').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/platform/gift-codes');
  }
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Gift Code review', level: 1 })).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Gift Code review queues' })).toBeVisible();
  const adapter = page.getByLabel('Installed ingestion adapter');
  await expect(adapter.getByRole('option', { name: 'json-feed-v1' })).toHaveCount(1);
  await adapter.selectOption('json-feed-v1');
  await expect(page.getByLabel('HTTPS JSON feed path')).toBeVisible();

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
});
