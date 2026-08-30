import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const fingerprints: Record<string, Record<'closeout' | 'ready', string>> = {
  desktop: {
    closeout: 'b723f20dd1a1b976eb270bc05d672673ae11145173c2ecb7562faeea656b458d',
    ready: 'fd61a9f060a3e2f91d11b3009fc9b21ab6e7debfad24781c795f212d6abc447c',
  },
  mobile: {
    closeout: '4c76b2ad60cf989492101f7d7ef673870f932b691a6cbb79ef8d26b0c7a4668d',
    ready: 'c6fe2044fed60b5a74e76b614ee7cee683f9654fd0b7df691613584cc55aabc7',
  },
};

async function openEventCommand(page: Page): Promise<Locator> {
  await page.goto('/login');
  await page.locator('#email').fill('event-command-visual@example.test');
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

  await page.goto('/events');
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: 'Event Command Visual' }).first().click();
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: 'Manage Event' }).click();
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  const command = page.locator('#event-command');
  await expect(command).toBeVisible();

  return command;
}

async function normalizeDynamicTimes(command: Locator): Promise<void> {
  await command.evaluate((element) => {
    for (const node of element.querySelectorAll('span, option')) {
      const text = node.textContent ?? '';
      if (!/\b20\d{2}\b/.test(text)) continue;
      if (/completed/i.test(text)) node.textContent = 'Fixture closeout · Completed';
      else if (/scheduled/i.test(text)) node.textContent = 'Fixture upcoming · Scheduled';
      else node.textContent = 'Fixture occurrence time';
    }
  });
}

async function fingerprint(command: Locator): Promise<string> {
  const screenshot = await command.screenshot({
    animations: 'disabled',
    caret: 'hide',
    scale: 'css',
  });

  return createHash('sha256').update(screenshot).digest('hex');
}

test('Event Command keeps closeout and readiness visible without responsive overflow', async ({
  page,
}, testInfo) => {
  const command = await openEventCommand(page);

  await expect(command.getByText('Closeout required', { exact: true })).toBeVisible();
  await expect(command.getByText('The Event result has not been recorded.')).toBeVisible();
  await expect(command.getByText('Owner: Results')).toBeVisible();
  await expect(command.getByRole('link', { name: 'Record results' })).toBeVisible();
  await expect(command.getByRole('combobox', { name: 'Occurrence' })).toBeVisible();
  await normalizeDynamicTimes(command);

  const closeoutHash = await fingerprint(command);

  const selector = command.getByRole('combobox', { name: 'Occurrence' });
  const optionValues = await selector
    .locator('option')
    .evaluateAll((options) => options.map((option) => (option as HTMLOptionElement).value));
  expect(optionValues).toHaveLength(2);
  await selector.selectOption(optionValues[1]);
  await page.waitForLoadState('networkidle');

  const refreshed = page.locator('#event-command');
  await expect(refreshed.getByText('Ready', { exact: true })).toBeVisible();
  await expect(
    refreshed.getByText('Owner: Alliance Content · Alliance strategy', { exact: true }),
  ).toBeVisible();
  await normalizeDynamicTimes(refreshed);

  const overflow = await refreshed.evaluate(
    (element) => element.scrollWidth > element.clientWidth,
  );
  expect(overflow).toBeFalsy();

  const readyHash = await fingerprint(refreshed);
  expect(
    { closeout: closeoutHash, ready: readyHash },
    `Update Event Command visual fingerprints for ${testInfo.project.name}`,
  ).toEqual(fingerprints[testInfo.project.name]);
});
