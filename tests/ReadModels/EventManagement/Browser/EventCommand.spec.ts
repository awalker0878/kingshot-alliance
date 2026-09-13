import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const fingerprints: Record<string, Record<'closeout' | 'ready', string>> = {
  desktop: {
    closeout: 'f68b65a20aa9baec1a15e336a56aa21d1e34ccf74a52905f638a50a4e44cb46e',
    ready: '01a618dd1798c3de4ab42b3f0638540e976d4a53179b626a02be20dc6b96408a',
  },
  mobile: {
    closeout: 'c8a8ca4cb35a5e74af0528da60fcff2eb2c6f61e6fd1db041d342371ecf3c723',
    ready: 'd51ac48f33cee8b8c2c6d80da51308659669ae162502de563d0c2ba6ef74ab82',
  },
};

async function openEventCommand(page: Page, eventName = 'Event Command Visual'): Promise<Locator> {
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
  await page.getByRole('link', { name: eventName }).first().click();
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

  const overflow = await refreshed.evaluate((element) => element.scrollWidth > element.clientWidth);
  expect(overflow).toBeFalsy();

  const readyHash = await fingerprint(refreshed);
  expect(
    { closeout: closeoutHash, ready: readyHash },
    `Update Event Command visual fingerprints for ${testInfo.project.name}`,
  ).toEqual(fingerprints[testInfo.project.name]);
});

test('Event occurrence history preserves drafts through paging and retry and opens older occurrences', async ({
  page,
}) => {
  const command = await openEventCommand(page, 'Event History Visual');
  const pager = command.locator('[data-event-catalogue="occurrence"]');
  await expect(pager.getByText('101 records')).toBeVisible();
  const selected = await command.getByRole('combobox', { name: 'Occurrence' }).inputValue();
  const title = page.getByRole('textbox', { name: 'Optional Event name', exact: true });
  await title.fill('Unsubmitted schedule draft');
  const firstValues = await command
    .locator('select option')
    .evaluateAll((nodes) => nodes.map((node) => (node as HTMLOptionElement).value));
  let inject = true;
  await page.route('**/events/*/manage?**', async (route) => {
    if (inject && route.request().url().includes('occurrence_cursor=')) {
      inject = false;
      await route.abort('failed');
      return;
    }
    await route.continue();
  });
  await pager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(pager.getByRole('alert')).toContainText('This page could not be loaded');
  await expect(title).toHaveValue('Unsubmitted schedule draft');
  await pager.getByRole('button', { name: 'Retry page', exact: true }).click();
  await expect(pager.getByRole('button', { name: 'First page', exact: true })).toBeVisible();
  await expect(title).toHaveValue('Unsubmitted schedule draft');
  await expect(command.getByRole('combobox', { name: 'Occurrence' })).toHaveValue(selected);
  const nextValues = await command
    .locator('select option')
    .evaluateAll((nodes) => nodes.map((node) => (node as HTMLOptionElement).value));
  expect(nextValues).toHaveLength(26);
  expect(
    nextValues.filter((id) => id !== selected).every((id) => !firstValues.includes(id)),
  ).toBeTruthy();
  await pager.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(pager.getByRole('button', { name: 'First page', exact: true })).toHaveCount(0);
  await expect(title).toHaveValue('Unsubmitted schedule draft');
  await pager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(pager.getByRole('button', { name: 'First page', exact: true })).toBeVisible();
  const older = nextValues.find((id) => id !== selected);
  expect(older).toBeTruthy();
  await command.getByRole('combobox', { name: 'Occurrence' }).selectOption(older!);
  await expect(command.getByText('Cancelled', { exact: true })).toBeVisible();
  await expect(command.getByRole('combobox', { name: 'Occurrence' })).toHaveValue(older!);
  await expect(page.locator('#schedule').getByRole('link').first()).toHaveAttribute(
    'href',
    `/events/${older}`,
  );
  expect(
    await command.evaluate((element) => element.scrollWidth > element.clientWidth),
  ).toBeFalsy();
});

test('Event phase and poll pages preserve independent drafts and saved member votes', async ({
  page,
}, testInfo) => {
  const command = await openEventCommand(page, 'Event History Visual');
  const occurrence = await command.getByRole('combobox', { name: 'Occurrence' }).inputValue();
  const phases = page.locator('#phases');
  const polls = page.locator('#polls');
  const phasePager = phases.locator('[data-event-catalogue="phase"]');
  const pollPager = polls.locator('[data-event-catalogue="poll"]');
  await expect(phasePager.getByText('61 records')).toBeVisible();
  await expect(pollPager.getByText('61 records')).toBeVisible();
  const phaseDraft = phases.getByPlaceholder('Phase name', { exact: true });
  const pollDraft = polls.getByPlaceholder('Poll question', { exact: true });
  await phaseDraft.fill('Unsubmitted phase draft');
  await pollDraft.fill('Unsubmitted poll draft');
  await phasePager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(phases.getByRole('button', { name: /History phase 25/ })).toBeVisible();
  await expect(polls.getByText('History poll 0', { exact: true })).toBeVisible();
  await pollPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(polls.getByText('History poll 25', { exact: true })).toBeVisible();
  await expect(phaseDraft).toHaveValue('Unsubmitted phase draft');
  await expect(pollDraft).toHaveValue('Unsubmitted poll draft');
  await expect(phases.getByRole('button', { name: /History phase 25/ })).toBeVisible();
  await phasePager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(phases.getByRole('button', { name: /History phase 60/ })).toBeVisible();
  await pollPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(polls.getByText('History poll 60', { exact: true })).toBeVisible();
  await expect(phaseDraft).toHaveValue('Unsubmitted phase draft');
  await expect(pollDraft).toHaveValue('Unsubmitted poll draft');

  await page.goto(`/events/${occurrence}`);
  const memberPolls = page.locator('section[aria-labelledby="polls-heading"]');
  const memberPager = memberPolls.locator('[data-event-catalogue="poll"]');
  await expect(memberPager.getByText('60 records')).toBeVisible();
  await memberPager.getByRole('button', { name: 'Next page', exact: true }).click();
  const question = testInfo.project.name === 'desktop' ? 'History poll 30' : 'History poll 31';
  const poll = memberPolls
    .locator('article')
    .filter({ has: page.getByText(question, { exact: true }) });
  const option = poll.getByRole('button', { name: 'History option 1', exact: true });
  const saved = page.waitForResponse(
    (response) => response.request().method() === 'PUT' && response.url().endsWith('/vote'),
  );
  await option.click();
  expect((await saved).status()).toBe(303);
  await page.waitForLoadState('networkidle');
  // The owner redirect may return to page one; navigate from a fresh catalogue in either case.
  await page.goto(`/events/${occurrence}`);
  await memberPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(option).toHaveAttribute('aria-pressed', 'true');
  await memberPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(memberPolls.locator('article')).toHaveCount(10);
  await expect(memberPolls.getByText('History poll 60', { exact: true })).toHaveCount(0);
  await memberPager.getByRole('button', { name: 'First page', exact: true }).click();
  await memberPager.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(option).toHaveAttribute('aria-pressed', 'true');
  expect(
    await memberPolls.evaluate((element) => element.scrollWidth > element.clientWidth),
  ).toBeFalsy();
});
