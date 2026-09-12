import { expect, test } from '@playwright/test';
import type { Locator } from '@playwright/test';

async function nextPage(section: Locator): Promise<void> {
  const firstId = await section.locator('li').first().getAttribute('data-history-id');
  if (!firstId) throw new Error('Expected a concrete history row before continuation.');
  await section.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(section.locator('li').first()).not.toHaveAttribute('data-history-id', firstId);
}

test('workflow histories page independently, retain drafts and expose retryable failures', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`transfer-history-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  await page.goto('/alliance/transfers/readiness');
  const card = page.locator('article').filter({
    has: page.getByRole('heading', {
      name: `Workflow History ${info.project.name}`,
      exact: true,
      level: 2,
    }),
  });
  await expect(card).toBeVisible();
  const draft = card
    .getByRole('group', { name: 'Manual planning blockers', exact: true })
    .getByRole('textbox', { name: 'Summary', exact: true });
  await draft.fill('Keep this unsaved draft');
  const blockers = card.getByTestId('transfer-blockers-history');
  await blockers.locator('summary').click();
  await expect(blockers.locator('li')).toHaveCount(25);
  await expect(blockers.getByRole('option', { name: 'Active (31)', exact: true })).toHaveCount(1);
  await expect(blockers.getByRole('option', { name: 'Resolved (53)', exact: true })).toHaveCount(1);
  const active = await blockers.locator('li strong').allTextContents();
  await nextPage(blockers);
  await expect(blockers.locator('li')).toHaveCount(6);
  active.push(...(await blockers.locator('li strong').allTextContents()));
  expect(new Set(active).size).toBe(31);
  await expect(blockers.getByRole('button', { name: 'Next page', exact: true })).toHaveCount(0);
  await expect(draft).toHaveValue('Keep this unsaved draft');

  await page.route(
    '**/participants/*/blockers?state=resolved',
    (route) => route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }),
    { times: 1 },
  );
  await blockers.getByRole('combobox').selectOption('resolved');
  await expect(blockers.getByRole('alert')).toContainText(
    'Transfer workflow history could not be loaded.',
  );
  await blockers.getByRole('button', { name: 'Reload history', exact: true }).click();
  await expect(blockers.locator('li')).toHaveCount(25);
  const resolved = await blockers.locator('li strong').allTextContents();
  await nextPage(blockers);
  await expect(blockers.locator('li')).toHaveCount(25);
  resolved.push(...(await blockers.locator('li strong').allTextContents()));
  await nextPage(blockers);
  await expect(blockers.locator('li')).toHaveCount(3);
  resolved.push(...(await blockers.locator('li strong').allTextContents()));
  expect(new Set(resolved).size).toBe(53);
  expect(resolved.every((text) => text.startsWith('resolved blocker'))).toBe(true);
  await blockers.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(blockers.locator('li')).toHaveCount(25);
  await expect(draft).toHaveValue('Keep this unsaved draft');

  const history = card.getByTestId('transfer-readiness-history');
  await history.locator('summary').click();
  await expect(history.locator('li')).toHaveCount(25);
  await nextPage(history);
  await expect(history.locator('li')).toHaveCount(25);
  await nextPage(history);
  await expect(history.locator('li')).toHaveCount(11);
  await expect(history.getByRole('button', { name: 'Next page', exact: true })).toHaveCount(0);
  await expect(history).toContainText('61 records in this history.');
  await expect(blockers.locator('li')).toHaveCount(25);
  await expect(draft).toHaveValue('Keep this unsaved draft');
  expect(errors).toEqual([]);
});
