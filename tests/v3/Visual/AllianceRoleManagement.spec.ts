import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('role-boundary-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  const switcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (
    (await switcher.isVisible()) &&
    /select governor/i.test((await switcher.textContent()) ?? '')
  ) {
    await switcher.click();
    await page
      .getByRole('listbox', { name: 'Active Governor' })
      .getByRole('option')
      .first()
      .click();
    await page.waitForURL('**/dashboard');
  }
}

test('new role stays editable with validation feedback through retained page updates', async ({
  page,
}, testInfo) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await login(page);
  await page.goto('/alliance/roles');
  const original = 'AAA Live Role ' + testInfo.project.name;
  const renamed = 'AAA Renamed Role ' + testInfo.project.name;
  await page.locator('#new-role-name').fill(original);
  await page.locator('aside form').getByLabel('Manage Alliance content', { exact: true }).check();
  await page.getByRole('button', { name: 'Create specialist role', exact: true }).click();
  const role = page
    .locator('article')
    .filter({ has: page.getByRole('heading', { name: original, exact: true }) });
  await expect(role).toBeVisible();
  await role.getByLabel('Role name', { exact: true }).fill('');
  await role.getByRole('button', { name: 'Update role', exact: true }).click();
  await expect(role.getByRole('alert')).toContainText(/name.*required/i);
  await expect(role.getByLabel('Role name', { exact: true })).toHaveValue('');
  await role.getByLabel('Role name', { exact: true }).fill(renamed);
  await role.getByRole('button', { name: 'Update role', exact: true }).click();
  const updated = page
    .locator('article')
    .filter({ has: page.getByRole('heading', { name: renamed, exact: true }) });
  await expect(updated).toBeVisible();
  await expect(updated.getByLabel('Role name', { exact: true })).toHaveValue(renamed);
  await expect(updated.getByRole('alert')).toHaveCount(0);
  await updated.getByRole('button', { name: 'Archive role', exact: true }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Archive role', exact: true }).click();
  await expect(updated).toHaveCount(0);
  await page.locator('#role-status').selectOption('archived');
  await page.getByRole('button', { name: 'Find roles', exact: true }).click();
  await expect(page.getByRole('heading', { name: renamed, exact: true })).toBeVisible();
  expect(errors).toEqual([]);
});

test('role choices page and search while retaining an explicitly selected role', async ({
  page,
}) => {
  await login(page);
  await page.goto('/alliance/members/bulk');
  const picker = page.locator('#bulk-role');
  await picker.focus();
  await expect(picker.locator('option')).toHaveCount(26);
  await page.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(picker.locator('option')).toHaveCount(15);
  await page.locator('#bulk-role-search').fill('Picker target 34');
  await page.getByRole('button', { name: 'Find roles', exact: true }).click();
  await expect(picker.locator('option')).toHaveCount(2);
  await picker.selectOption({ label: 'Picker target 34' });
  const selected = await picker.inputValue();
  await page.locator('#bulk-role-search').fill('Picker target 00');
  await page.getByRole('button', { name: 'Find roles', exact: true }).click();
  await expect(picker.locator('option')).toHaveCount(3);
  await expect(picker).toHaveValue(selected);
  await expect(picker.locator('option:checked')).toHaveText('Picker target 34');
});
