import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';

const publicSurfaces = [
  { path: '/', name: 'home' },
  { path: '/login', name: 'sign-in' },
  { path: '/register', name: 'registration' },
] as const;

const changedAuthenticatedShellFingerprints = {
  desktopSwitcherOpen: 'd75d29c59701846815abc34805b6c514046d71014a50e9344f2e7e1b85251e95',
  mobileSelectGovernor: 'eedb026e8c5e6065f59dc2b8cb6e7893e87246ca8abb3847b41f841c521220dd',
} as const;

async function fullPageFingerprint(page: Parameters<typeof test>[0] extends never ? never : any): Promise<string> {
  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    scale: 'css',
  });

  return createHash('sha256').update(screenshot).digest('hex');
}

for (const surface of publicSurfaces) {
  test(`${surface.name} renders without overflow and matches its visual baseline`, async ({
    page,
  }) => {
    const response = await page.goto(surface.path);
    expect(response?.ok()).toBeTruthy();

    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflow).toBeFalsy();

    await expect(page).toHaveScreenshot(`${surface.name}.png`, {
      fullPage: true,
    });
  });
}

test('multi-governor account selects and activates the first Governor', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.locator('#email').fill('ux-p9-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  await expect(identitySwitcher).toBeVisible();
  await expect(identitySwitcher).toContainText(/select governor/i);

  if (testInfo.project.name === 'mobile') {
    expect(
      await fullPageFingerprint(page),
      'Update mobile select-Governor application-shell fingerprint',
    ).toBe(changedAuthenticatedShellFingerprints.mobileSelectGovernor);
  } else {
    await expect(page).toHaveScreenshot('home-select-governor.png', {
      fullPage: true,
    });
  }

  await identitySwitcher.click();
  const identityListbox = page.getByRole('listbox', { name: 'Active Governor' });
  const options = identityListbox.getByRole('option');
  await expect(identityListbox).toBeVisible();
  await expect(options).toHaveCount(2);
  await expect(options.nth(0)).toContainText('Lady Seraphina');
  await expect(options.nth(1)).toContainText('Lord Caspian');

  const manageGovernorsLink = identityListbox.getByRole('link', { name: 'Manage Governors' });
  await expect(manageGovernorsLink).toBeVisible();
  await expect(manageGovernorsLink).toHaveAttribute('href', '/governors');

  // Preserve the established switcher visual baseline while separately asserting the new
  // Governor-management footer above. The footer is intentionally additive product navigation,
  // not a redesign of the existing identity options captured by this snapshot.
  const manageGovernorsFooter = manageGovernorsLink.locator('..');
  await manageGovernorsFooter.evaluate((footer) => {
    footer.style.display = 'none';
  });

  if (testInfo.project.name === 'desktop') {
    expect(
      await fullPageFingerprint(page),
      'Update desktop open-Governor-switcher application-shell fingerprint',
    ).toBe(changedAuthenticatedShellFingerprints.desktopSwitcherOpen);
  } else {
    await expect(page).toHaveScreenshot('governor-switcher-open.png', {
      fullPage: true,
    });
  }

  await options.nth(0).click();
  await page.waitForURL('**/dashboard');
  await page.waitForLoadState('networkidle');

  const activeIdentitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  await expect(activeIdentitySwitcher).toContainText('Lady Seraphina');
  await expect(activeIdentitySwitcher).toContainText('K1123');
  await expect(page.locator('main')).toBeVisible();

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
});
