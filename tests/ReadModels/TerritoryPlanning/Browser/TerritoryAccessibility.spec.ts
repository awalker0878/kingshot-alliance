import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function activateTerritoryGovernor(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('territory-visual@example.test');
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

async function openHiveBuilder(page: Page): Promise<void> {
  await activateTerritoryGovernor(page);
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: 'Hive Builder' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Hive Builder' })).toBeVisible();
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    ),
  ).toBeTruthy();
}

test('KM-16 territory editing is reachable by keyboard and semantic controls', async ({ page }) => {
  await openHiveBuilder(page);

  const canvas = page.getByLabel('Interactive Kingdom territory map editor', { exact: true });
  await expect(canvas).toBeVisible();
  await canvas.focus();
  await expect(canvas).toBeFocused();
  await page.keyboard.press('Home');
  await page.keyboard.press('ArrowRight');

  const filter = page.getByLabel('Filter placed objects');
  await expect(filter).toBeVisible();
  await filter.focus();
  await expect(filter).toBeFocused();
  await filter.fill('North Star');

  const objectTable = page.locator('table').filter({ hasText: 'North Star' }).first();
  await expect(objectTable).toBeVisible();
  await expect(objectTable.getByText('North Star', { exact: true })).toBeVisible();

  const coordinate = objectTable.locator('input[type="number"]').first();
  await expect(coordinate).toBeVisible();
  await coordinate.focus();
  await expect(coordinate).toBeFocused();
  const original = Number(await coordinate.inputValue());
  await coordinate.press('ArrowUp');
  expect(Number(await coordinate.inputValue())).toBe(original + 1);

  await expectNoHorizontalOverflow(page);
});

test('KM-16 territory touch navigation remains usable on compact and tablet profiles', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === 'desktop', 'Touch drawer evidence is for compact/tablet profiles.');
  await openHiveBuilder(page);

  const objectsButton = page.getByRole('button', { name: 'Objects', exact: true });
  if (await objectsButton.isVisible()) {
    const box = await objectsButton.boundingBox();
    expect(box?.height ?? 0).toBeGreaterThanOrEqual(40);
    await objectsButton.tap();
  }

  const filter = page.getByLabel('Filter placed objects');
  await expect(filter).toBeVisible();
  await filter.tap();
  await filter.fill('North Star');
  await expect(page.getByText('North Star', { exact: true }).first()).toBeVisible();
  await expectNoHorizontalOverflow(page);
});

test('KM-16 territory workspace applies RTL direction without losing semantic access', async ({ page }) => {
  await openHiveBuilder(page);
  await page.evaluate(() => window.localStorage.setItem('kingshot.locale', 'ar'));
  await page.reload();
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.locator('canvas[tabindex="0"]').first()).toBeVisible();
  await expect(page.locator('table').first()).toBeVisible();
  await expectNoHorizontalOverflow(page);
});
