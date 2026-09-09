import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

type Surface =
  | 'rallyBuilder'
  | 'memberProfile'
  | 'transferCampaign'
  | 'intelligenceTimeline'
  | 'allianceCommand'
  | 'officerBriefs'
  | 'assistant';

const fingerprints: Record<string, Record<Surface, string>> = {
  desktop: {
    rallyBuilder: 'cdb85d962ede7f68b9ee078625b46aa19f2f25fdcf3237db79fd60a6bb887601',
    memberProfile: '019114f75edb7fe116c3e17aeb77cb43874111d8b037ce960267982bdc42521e',
    transferCampaign: '0f67985ea8fc84cc08cdadeeb369f2e86d1fcfc16fb85b4bed236bbc5138651c',
    intelligenceTimeline: 'e2e727d74fd513e2479ca1730bd79c7117743cf9f44003c05e8e919096c78a50',
    allianceCommand: 'de7aab8f5eee1fde41a164f08ec9c881fa91f56f8650394b9463123a8d70aa01',
    officerBriefs: 'd15773bc8c382aeff5ab4453b076cd9868009b57af972f0df017c70025c88143',
    assistant: '8cc124ee1ba262b0eaf5e6ca56b4b764bb52d078eaa408cf26b5390ddd4cf163',
  },
  mobile: {
    rallyBuilder: '83c0f81ab893ae413016045bd4e64144fc1e00731b58a5a84e39769f8fd67d8a',
    memberProfile: '66b9033ff1029f8fb35e6099a5982e39f2a26887446c18e92a6d489cd91c7cd1',
    transferCampaign: '984cf1ee925750629cbbf6df7298866d737ed18cf02e656f91a44b50ab1301b4',
    intelligenceTimeline: '23da633df74ceeb3e68a3e688b6d58ad34f22ffe2fec3fd08c2cd7ce77edd46e',
    allianceCommand: 'cc53e997be8fb9c6762df3590b17a0518455d180307adb283567eaaa83f1f970',
    officerBriefs: 'c9a7c40fb325092bd7349918b555c7f2e8610e90399e69ff7584c5738343529d',
    assistant: 'b7069af6767de075ff6b625732f904a08938826361e58f898f802b7c15ddcc90',
  },
};

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('capability-acceptance-visual@example.test');
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
}

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);
}

async function openEventManagement(page: Page): Promise<void> {
  await page.goto('/events');
  await settle(page);
  await page.getByRole('link', { name: 'Capability Acceptance Bear Hunt' }).first().click();
  await settle(page);
  await page.getByRole('link', { name: 'Manage Event' }).click();
  await settle(page);
}

async function openMemberProfile(page: Page): Promise<void> {
  await page.goto('/alliance/roster/intelligence');
  await settle(page);
  await page.getByRole('link', { name: 'Acceptance Marshal' }).first().click();
  await settle(page);
}

async function openTransferCampaign(page: Page): Promise<void> {
  await page.goto('/alliance/recruitment');
  await settle(page);
  await page.getByRole('link', { name: 'Acceptance Candidate' }).first().click();
  await settle(page);
}

async function openIntelligenceTimeline(page: Page): Promise<void> {
  await page.goto('/alliance/kingdom-alliances');
  await settle(page);
  await page.getByRole('link', { name: 'Timeline Watch' }).first().click();
  await settle(page);
}

async function normalizeDynamicText(target: Locator): Promise<void> {
  await target.evaluate((element) => {
    for (const node of element.querySelectorAll('time, span, p, dd')) {
      if (node.children.length > 0) continue;
      const text = node.textContent ?? '';
      const stableIdentifiers = text
        .replace(
          /\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/gi,
          'fixture-id',
        )
        .replace(/\b[0-9a-hjkmnp-tv-z]{26}\b/gi, 'fixture-id');
      if (stableIdentifiers !== text) {
        node.textContent = stableIdentifiers;
      } else if (
        /\b20\d{2}\b/.test(text) ||
        /\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/.test(text) ||
        /\b(?:second|minute|hour|day|week|month|year)s? ago\b/i.test(text)
      ) {
        node.textContent = 'Fixture date';
      }
    }
  });
}

async function fingerprint(target: Locator): Promise<string> {
  await normalizeDynamicText(target);
  const text = await target.innerText();
  return createHash('sha256').update(text.replace(/\s+/g, ' ').trim()).digest('hex');
}

async function captureSurface(page: Page, surface: Surface): Promise<string> {
  switch (surface) {
    case 'rallyBuilder':
      await openEventManagement(page);
      return fingerprint(page.locator('main'));
    case 'memberProfile':
      await openMemberProfile(page);
      return fingerprint(page.locator('main'));
    case 'transferCampaign':
      await openTransferCampaign(page);
      return fingerprint(page.locator('main'));
    case 'intelligenceTimeline':
      await openIntelligenceTimeline(page);
      return fingerprint(page.locator('main'));
    case 'allianceCommand':
      await page.goto('/alliance/command');
      await settle(page);
      return fingerprint(page.locator('main'));
    case 'officerBriefs':
      await page.goto('/alliance/officer-briefs');
      await settle(page);
      return fingerprint(page.locator('main'));
    case 'assistant':
      await page.goto('/assistant');
      await settle(page);
      return fingerprint(page.locator('main'));
  }
}

const surfaces: Surface[] = [
  'rallyBuilder',
  'memberProfile',
  'transferCampaign',
  'intelligenceTimeline',
  'allianceCommand',
  'officerBriefs',
  'assistant',
];

test('capability acceptance surfaces remain visually and semantically stable', async ({ page }, testInfo) => {
  // This acceptance case intentionally traverses seven authenticated surfaces. The
  // default 30s Playwright budget is suitable for focused cases but made this
  // aggregate matrix flaky under CI load, so give only this test a realistic cap.
  test.setTimeout(90_000);

  await login(page);

  const viewport = testInfo.project.name;
  const actual = {} as Record<Surface, string>;
  for (const surface of surfaces) {
    actual[surface] = await captureSurface(page, surface);
  }

  expect(actual, `Update capability matrix fingerprints for ${viewport}`).toEqual(fingerprints[viewport]);
});
