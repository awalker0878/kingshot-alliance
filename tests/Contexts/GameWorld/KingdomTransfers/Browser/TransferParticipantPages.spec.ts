import { expect, test } from '@playwright/test';

test('participant pages retain complete totals, independent drafts and retryable navigation', async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill(`transfer-pages-${info.project.name}@example.test`);
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');
  const overview = page.getByRole('region', { name: 'Officer overview', exact: true });
  await expect(overview).toContainText('36 remain unassessed in this overview');
  await expect(overview).toContainText('Further assessment needed');
  await expect(overview).not.toContainText(
    'Current Transfer participants have no verification blockers',
  );
  await page.goto('/alliance/transfers/readiness');
  const pager = page.getByTestId('transfer-participant-pagination');
  const cards = page.locator('[data-transfer-participant]');
  const first = cards.first();
  const draft = first
    .getByRole('group', { name: 'Manual planning blockers', exact: true })
    .getByRole('textbox', { name: 'Summary', exact: true });
  await expect(pager).toContainText('25 participants on this page; 61 in this view.');
  await expect(cards).toHaveCount(25);
  const firstIds = await cards.evaluateAll((nodes) =>
    nodes.map((node) => node.getAttribute('data-transfer-participant')),
  );
  await draft.fill('Private unsaved page draft');
  await page.route(
    '**/alliance/transfers/readiness?participant_cursor=*',
    (route) => route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }),
    { times: 1 },
  );
  await pager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(pager.getByRole('alert')).toContainText('The participant page could not be loaded.');
  await expect(draft).toHaveValue('Private unsaved page draft');
  await expect(cards).toHaveCount(25);
  await pager.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(cards.first()).not.toHaveAttribute('data-transfer-participant', firstIds[0]!);
  const secondIds = await cards.evaluateAll((nodes) =>
    nodes.map((node) => node.getAttribute('data-transfer-participant')),
  );
  await expect(cards).toHaveCount(25);
  await pager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(pager).toContainText('11 participants on this page; 61 in this view.');
  await expect(cards).toHaveCount(11);
  const thirdIds = await cards.evaluateAll((nodes) =>
    nodes.map((node) => node.getAttribute('data-transfer-participant')),
  );
  expect(new Set([...firstIds, ...secondIds, ...thirdIds]).size).toBe(61);
  await expect(pager.getByRole('button', { name: 'Next page', exact: true })).toHaveCount(0);
  await pager.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(cards.first()).toHaveAttribute('data-transfer-participant', firstIds[0]!);
  await expect(draft).toHaveValue('Private unsaved page draft');
  await expect(pager).toContainText('25 participants on this page; 61 in this view.');
  expect(errors).toEqual([]);
});
