import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const assistantVisualFingerprints: Record<string, string> = {
  desktop: 'd07e2b3564ec7f9a1c311fb93a58148e287bb081703c805646da804f215bae23',
  mobile: '3a49c2e28f4fa4a946eafce4e0314fb39eeb3cc5db9731e5f422cd85eec8980d',
};

const citedSwordlandResponse = {
  intent: 'event_roster_self',
  status: 'answered',
  messageKey: 'assistant.answers.eventTimeRostered',
  messageParameters: {
    event: 'Swordland',
    startsAt: '2026-08-29T20:00:00+00:00',
    roster: 'Combatants',
    role: 'Rally Lead',
    slot: 7,
    status: 'assigned',
  },
  classifications: ['operational_fact'],
  evidence: [
    {
      id: 'event-visual',
      sourceType: 'event',
      sourceId: 'visual-event',
      title: 'Swordland · Aug 29',
      classification: 'operational_fact',
      statement: '2026-08-29T20:00:00+00:00',
      occurredAt: '2026-08-29T20:00:00+00:00',
      updatedAt: '2026-08-24T15:00:00+00:00',
      href: '/events/01K00000000000000000000000',
      metadata: {},
    },
    {
      id: 'roster-visual',
      sourceType: 'roster',
      sourceId: 'visual-roster',
      title: 'Swordland Combatants',
      classification: 'operational_fact',
      statement: 'assigned',
      occurredAt: null,
      updatedAt: '2026-08-24T15:05:00+00:00',
      href: '/events/01K00000000000000000000000',
      metadata: { role: 'Rally Lead', slot: 7, status: 'assigned' },
    },
  ],
  citations: [
    {
      evidenceId: 'event-visual',
      sourceType: 'event',
      sourceId: 'visual-event',
      title: 'Swordland · Aug 29',
      classification: 'operational_fact',
      occurredAt: '2026-08-29T20:00:00+00:00',
      updatedAt: '2026-08-24T15:00:00+00:00',
      href: '/events/01K00000000000000000000000',
    },
    {
      evidenceId: 'roster-visual',
      sourceType: 'roster',
      sourceId: 'visual-roster',
      title: 'Swordland Combatants',
      classification: 'operational_fact',
      occurredAt: null,
      updatedAt: '2026-08-24T15:05:00+00:00',
      href: '/events/01K00000000000000000000000',
    },
  ],
  ambiguity: null,
  suggestedQuestions: ['swordland_roster', 'next_event', 'bear_hunt_guide', 'observation'],
};

async function openAssistant(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('screenshot-visual@example.test');
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

  await page.goto('/assistant');
  await page.waitForLoadState('networkidle');
}

async function mockCitedAnswer(page: Page): Promise<void> {
  await page.route('**/assistant/ask', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(citedSwordlandResponse),
    });
  });
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();
}

test('Alliance Assistant renders a cited Swordland roster answer on desktop and mobile', async ({
  page,
}, testInfo) => {
  await mockCitedAnswer(page);
  await openAssistant(page);
  await page.evaluate(() => document.fonts.ready);

  await expect(page.getByRole('heading', { name: 'Ask your Alliance', level: 1 })).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'What time is Swordland and am I rostered?' }),
  ).toBeVisible();
  await expect(page.getByRole('button', { name: 'What is my next Event?' })).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'What does our Bear Hunt guide say?' }),
  ).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'What have we observed about our opponent?' }),
  ).toBeVisible();

  const assistant = page.getByLabel('Alliance Assistant');
  await page.getByRole('button', { name: 'What time is Swordland and am I rostered?' }).click();
  await expect(assistant.getByText(/Swordland starts/)).toBeVisible();
  await expect(assistant.getByText(/Rally Lead/)).toBeVisible();
  await expect(page.getByRole('region', { name: 'Sources used' })).toBeVisible();
  await expect(page.getByText('Event', { exact: true }).last()).toBeVisible();
  await expect(page.getByText('Roster', { exact: true }).last()).toBeVisible();
  await expectNoHorizontalOverflow(page);

  const screenshot = await page.locator('main').screenshot({
    animations: 'disabled',
    caret: 'hide',
    path: testInfo.outputPath('alliance-assistant.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');
  const expectedFingerprint = assistantVisualFingerprints[testInfo.project.name];

  expect(
    actualFingerprint,
    `Update Alliance Assistant visual fingerprint for ${testInfo.project.name}`,
  ).toBe(expectedFingerprint);
});

test('Alliance Assistant supports keyboard submission and a localized non-overflowing first-use state', async ({
  page,
}) => {
  await mockCitedAnswer(page);
  await openAssistant(page);

  const question = page.getByLabel('Ask your Alliance');
  const assistant = page.getByLabel('Alliance Assistant');
  await question.fill('What time is Swordland and am I rostered?');
  await question.press('Enter');
  await expect(assistant.getByText(/Swordland starts/)).toBeVisible();
  await expect(assistant.getByText(/Rally Lead/)).toBeVisible();

  await page.evaluate(() => window.localStorage.setItem('kingshot.locale', 'de'));
  await page.reload();
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  await expect(page.locator('html')).toHaveAttribute('lang', 'de');
  await expect(page.getByRole('heading', { name: 'Frag deine Allianz', level: 1 })).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Wann ist Swordland und bin ich im Roster?' }),
  ).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Was haben wir über unseren Gegner beobachtet?' }),
  ).toBeVisible();
  await expectNoHorizontalOverflow(page);
});
