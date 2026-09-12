import { expect, test } from '@playwright/test';

test('Governance choices and catalogues retain drafts, recover pages and commit selected authority', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`governance-catalogues-${info.project.name}@example.test`);
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
  await page.goto('/confirm-password');
  await page.locator('#confirm-password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('confirm-password'));
  await page.goto('/alliance/settings/kingdom/roles');
  const assignment = page.locator('form').filter({ has: page.locator('#assignment-player') });
  const reason = assignment.locator('input[maxlength="500"]');
  await reason.fill('Retained Governance assignment intent.');
  const governor = page.locator('#assignment-player');
  const choices = governor.locator('..');
  await governor.focus();
  await expect(governor.locator('option')).toHaveCount(26);
  await choices.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(governor.locator('option').nth(1)).toHaveText('Governance Governor 035');
  await governor.selectOption({ label: 'Governance Governor 030' });
  const selected = await governor.inputValue();
  await page.route('**/governance/choices/players?*', async (route) => {
    await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    await page.unroute('**/governance/choices/players?*');
  });
  await choices.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(choices.getByRole('alert')).toBeVisible();
  await choices.getByRole('button', { name: 'Retry choices', exact: true }).click();
  await expect(choices.getByRole('alert')).toHaveCount(0);
  await expect(governor).toHaveValue(selected);
  await expect(governor.locator('option', { hasText: 'Governance Governor 010' })).toHaveCount(1);
  await expect(reason).toHaveValue('Retained Governance assignment intent.');
  const catalogue = page.locator('[data-governance-catalogue="roles"]');
  await page.route('**/alliance/settings/kingdom/roles?*', async (route) => {
    await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    await page.unroute('**/alliance/settings/kingdom/roles?*');
  });
  await catalogue.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(catalogue.getByRole('alert')).toBeVisible();
  await catalogue.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(catalogue.getByRole('alert')).toHaveCount(0);
  await expect(page.locator('article').filter({ hasText: 'Governance Role 035' })).toBeVisible();
  await expect(reason).toHaveValue('Retained Governance assignment intent.');
  await expect(governor).toHaveValue(selected);
  await page.locator('#assignment-role-search').fill('Role 000');
  await page.locator('#assignment-role-search').press('Enter');
  await page.locator('#assignment-role').selectOption({ label: 'Governance Role 000' });
  const committed = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      response.url().endsWith('/alliance/settings/kingdom/roles'),
  );
  await assignment.getByRole('button', { name: 'Assign', exact: true }).click();
  expect((await committed).status()).toBeLessThan(400);
  await expect(reason).toHaveValue('');
  await expect(governor).toHaveValue(selected);
  await page.goto('/alliance/settings/kingdom/governance/authority?permission=events.kingdom.view');
  const holderPager = page.locator('[data-governance-catalogue="holders"]');
  await holderPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(page.getByText('Governance Governor 035', { exact: true })).toBeVisible();
  await page.goto('/alliance/settings/kingdom/governance/history');
  await expect(page.getByText('private-operator@example.test')).toHaveCount(0);
  await expect(
    page.getByText(
      'Private recovery incident and operator details must not reach Kingdom history.',
    ),
  ).toHaveCount(0);
  await page
    .locator('[data-governance-catalogue="history"]')
    .getByRole('button', { name: 'Next page', exact: true })
    .click();
  await expect(
    page
      .locator('[data-governance-catalogue="history"]')
      .getByRole('button', { name: 'First page', exact: true }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
