import { expect, test } from '@playwright/test';

test.use({ timezoneId: 'America/Toronto' });

test('event agenda and details honor the account timezone when the browser differs', async ({
  page,
}) => {
  await page.goto('/login');
  await page.locator('#email').fill('event-command-visual@example.test');
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

  await page.goto('/events');
  await page.waitForLoadState('networkidle');
  await expect(page.getByTestId('event-display-timezone')).toHaveText('Your time · UTC');
  const payload = JSON.parse((await page.locator('script[data-page="app"]').textContent()) ?? '{}');
  expect(payload.props.userTimezone).toBe('UTC');
  const event = payload.props.events.find(
    (item: { title: string }) => item.title === 'Event Command Visual',
  );
  expect(event).toBeTruthy();
  const locale = (await page.locator('html').getAttribute('lang')) ?? 'en';
  const expectedClock = new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(event.startsAt));
  const agendaRow = page.locator(`[data-testid="event-agenda-row"][data-event-id="${event.id}"]`);
  await expect(agendaRow).toContainText(expectedClock);
  await expect(agendaRow).toContainText('UTC');

  await agendaRow.locator(`a[href="/events/${event.id}"]`).first().click();
  await expect(page.getByTestId('event-display-timezone')).toHaveText('Your time · UTC');
  const expectedDate = new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(event.startsAt));
  await expect(page.getByText(expectedDate, { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Event time · UTC', { exact: true })).toBeVisible();
});
