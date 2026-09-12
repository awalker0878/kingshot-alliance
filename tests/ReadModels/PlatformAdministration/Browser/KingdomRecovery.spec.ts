import { expect, test } from '@playwright/test';

test('Kingdom recovery searches complete choices, retains paging drafts, resets scope and reports failures', async ({ page }, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`platform-catalogues-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/confirm-password');
  await page.locator('#confirm-password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('confirm-password'));
  await page.goto('/platform/kingdom-governance-recovery');
  const kingdom = page.locator('#recovery-kingdom');
  const governors = page.locator('#recovery-player');
  const choices = governors.locator('..');
  const targetKingdom = info.project.name === 'mobile' ? '830260' : '830259';
  await expect(governors).toBeDisabled();
  await page.locator('#recovery-kingdom-search').fill(targetKingdom);
  await page.locator('#recovery-kingdom-search').press('Enter');
  await expect(kingdom.locator('option')).toHaveCount(2);
  await kingdom.selectOption({ label: `#${targetKingdom}` });
  await governors.focus();
  await expect(governors.locator('option')).toHaveCount(26);
  await choices.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(governors.locator('option').nth(1)).toHaveText('Recovery governor 0025');
  await governors.selectOption({ label: 'Recovery governor 0030' });
  const selectedId = await governors.inputValue();
  await page.locator('#recovery-reason').fill('Verified recovery incident retained while paging.');
  await page.route('**/kingdom-governance-recovery/choices/players?*', async (route) => {
    await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    await page.unroute('**/kingdom-governance-recovery/choices/players?*');
  });
  await choices.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(choices.getByRole('alert')).toBeVisible();
  await choices.getByRole('button', { name: 'Retry choices', exact: true }).click();
  await expect(choices.getByRole('alert')).toHaveCount(0);
  await expect(governors.locator('option', { hasText: 'Recovery governor 0050' })).toHaveCount(1);
  await expect(governors).toHaveValue(selectedId);
  await expect(page.locator('#recovery-reason')).toHaveValue('Verified recovery incident retained while paging.');
  // Changing the governing Kingdom clears all target-dependent intent.
  await kingdom.selectOption('');
  await expect(governors).toHaveValue('');
  await expect(governors).toBeDisabled();
  await expect(page.locator('#recovery-reason')).toHaveValue('');
  await kingdom.selectOption({ label: `#${targetKingdom}` });
  await page.locator('#recovery-player-search').fill('1000');
  await page.locator('#recovery-player-search').press('Enter');
  await expect(governors.locator('option')).toHaveCount(2);
  await governors.selectOption({ label: 'Recovery governor 1000' });
  await page.locator('#recovery-reason').fill('Confirmed remote administrator recovery incident.');
  await page.route('**/platform/kingdom-governance-recovery', async (route) => {
    if (route.request().method() === 'POST') {
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
      await page.unroute('**/platform/kingdom-governance-recovery');
    } else await route.continue();
  });
  await page.locator('button[type="submit"]').click();
  await expect(page.locator('form').getByRole('alert')).toBeVisible();
  await expect(page.locator('#recovery-reason')).toHaveValue('Confirmed remote administrator recovery incident.');
  const submitted = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/platform/kingdom-governance-recovery'));
  await page.locator('button[type="submit"]').click();
  expect((await submitted).status()).toBeLessThan(400);
  await expect(page.locator('#recovery-reason')).toHaveValue('');
  await expect(page.locator('form').getByRole('alert')).toHaveCount(0);
  expect(errors).toEqual([]);
});
