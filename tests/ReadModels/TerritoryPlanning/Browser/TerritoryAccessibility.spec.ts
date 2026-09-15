import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const visualTime = new Date('2026-09-14T12:00:00Z');

async function login(page: Page): Promise<void> {
  await page.clock.setFixedTime(visualTime);
  await page.goto('/login');
  await page.locator('#email').fill('territory-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
}

async function openExplorer(page: Page): Promise<void> {
  await login(page);

  for (let attempt = 0; attempt < 3; attempt += 1) {
    await page.goto('/territory/explore');
    await page.waitForLoadState('networkidle');
    if (new URL(page.url()).pathname === '/territory/explore') return;

    await page.goto('/dashboard');
    const switches = page.locator('button[aria-haspopup="listbox"]:visible');
    const switchCount = await switches.count();
    let activated = false;
    for (let index = 0; index < switchCount; index += 1) {
      await switches.nth(index).click();
      const listbox = page.getByRole('listbox').filter({ visible: true }).last();
      if ((await listbox.count()) && (await listbox.getByRole('option').count())) {
        await listbox.getByRole('option').first().click();
        await page.waitForLoadState('networkidle');
        activated = true;
        break;
      }
      await page.keyboard.press('Escape');
    }
    if (!activated) break;
  }

  throw new Error(`Unable to activate a Governor and open Territory Explorer; ended at ${page.url()}`);
}

test('@km16 Explorer exposes semantic keyboard, touch, responsive and RTL journeys', async (
  { page },
  testInfo,
) => {
  await openExplorer(page);

  if (testInfo.project.name === 'rtl-tablet') {
    await page.evaluate(() => window.localStorage.setItem('kingshot.locale', 'ar'));
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  } else {
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  }

  const compactNavigation = page.locator('nav[aria-label]:visible').first();
  if (await compactNavigation.isVisible()) {
    const compactButtons = compactNavigation.getByRole('button');
    expect(await compactButtons.count()).toBeGreaterThanOrEqual(3);
    await compactButtons.last().click();
  }

  const semanticList = page.locator('ul[aria-label]:visible').filter({
    has: page.locator('button[aria-pressed]'),
  }).first();
  await expect(semanticList).toBeVisible();
  const objectButton = semanticList.locator('button[aria-pressed]').first();
  await expect(objectButton).toBeVisible();
  await objectButton.focus();
  await expect(objectButton).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(objectButton).toHaveAttribute('aria-pressed', 'true');

  if (await compactNavigation.isVisible()) {
    await compactNavigation.getByRole('button').first().click();
  }

  const minimap = page.locator('svg[role="button"][tabindex="0"]:visible').first();
  await expect(minimap).toBeVisible();
  const xCoordinate = page.locator('input[type="number"]').first();
  const keyboardXBefore = Number(await xCoordinate.inputValue());
  await minimap.focus();
  await expect(minimap).toBeFocused();
  await page.keyboard.press('ArrowRight');
  await expect.poll(async () => Number(await xCoordinate.inputValue())).not.toBe(keyboardXBefore);

  if (['mobile', 'tablet', 'rtl-tablet'].includes(testInfo.project.name)) {
    const touchXBefore = Number(await xCoordinate.inputValue());
    const box = await minimap.boundingBox();
    expect(box).not.toBeNull();
    await minimap.tap({ position: { x: Math.max(1, (box?.width ?? 2) * 0.75), y: Math.max(1, (box?.height ?? 2) * 0.5) } });
    await expect.poll(async () => Number(await xCoordinate.inputValue())).not.toBe(touchXBefore);
  }

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
});
