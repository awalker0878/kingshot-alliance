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
    memberProfile: 'dda039ca90fbe8bbbaef937210a6761c2e6332aa972de3b9d65428ca6e68f9ed',
    transferCampaign: '108d65aedb6e80d979d220b44ef2b66490b9a0b0c9ff6064bb23be75fcab7e43',
    intelligenceTimeline: 'c1b0e87db1bb579d67d45eb7427a2aed084ed1f8f3a2154364fc7e3563207d4f',
    allianceCommand: 'de7aab8f5eee1fde41a164f08ec9c881fa91f56f8650394b9463123a8d70aa01',
    officerBriefs: 'd15773bc8c382aeff5ab4453b076cd9868009b57af972f0df017c70025c88143',
    assistant: '8cc124ee1ba262b0eaf5e6ca56b4b764bb52d078eaa408cf26b5390ddd4cf163',
  },
  mobile: {
    rallyBuilder: '83c0f81ab893ae413016045bd4e64144fc1e00731b58a5a84e39769f8fd67d8a',
    memberProfile: '3274b5af5a93a70675586f333634426889b3a23a55fed781252c7f176bbc38d6',
    transferCampaign: '2ff40faaae87d4ee51f359cdc8562fdf44b035f86a1e52296409457349edf132',
    intelligenceTimeline: 'b077905b2e999bc585f1d85daa68868eccccec11aefe4a7f5ba5a6e070adb67a',
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
        /\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/.test(text)
      ) {
        node.textContent = 'Fixture date';
      }
    }
  });
}

async function screenshotHash(target: Locator): Promise<string> {
  await target.evaluate(
    (element) =>
      new Promise<void>((resolve) => {
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
        const top = element.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: Math.max(0, top - 112), left: 0 });
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
      }),
  );
  await normalizeDynamicText(target);
  const screenshot = await target.screenshot({
    animations: 'disabled',
    caret: 'hide',
    scale: 'css',
  });

  return createHash('sha256').update(screenshot).digest('hex');
}

async function assertAccessibleSurface(page: Page, target: Locator): Promise<void> {
  await expect(target).toBeVisible();
  await expect(page.locator('main')).toHaveCount(1);
  await expect(target.getByRole('heading').first()).toBeVisible();

  const overflow = await target.evaluate((element) => element.scrollWidth > element.clientWidth);
  expect(overflow).toBeFalsy();

  const unnamedControls = await target.locator('button, input, select, textarea, a[href]').evaluateAll(
    (controls) => controls.filter((control) => {
      const element = control as HTMLElement;
      if (element.offsetParent === null || element.getAttribute('aria-hidden') === 'true') return false;
      const labelledBy = element.getAttribute('aria-labelledby');
      const ariaLabel = element.getAttribute('aria-label');
      const title = element.getAttribute('title');
      const text = element.textContent?.trim();
      const id = element.getAttribute('id');
      const explicitLabel = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;

      return !labelledBy && !ariaLabel && !title && !text && !explicitLabel;
    }).length,
  );
  expect(unnamedControls, 'Every visible control needs a screen-reader name').toBe(0);

  const focusable = target.locator('a[href]:visible, button:visible, input:visible, select:visible, textarea:visible').first();
  if (await focusable.count()) {
    await focusable.focus();
    await expect(focusable).toBeFocused();
    await page.keyboard.press('Tab');
    const activeTag = await page.evaluate(() => document.activeElement?.tagName ?? '');
    expect(activeTag).not.toBe('BODY');
  }
}

test('capability acceptance surfaces remain visually and semantically stable', async ({
  page,
}, testInfo) => {
  await login(page);
  const actual = {} as Record<Surface, string>;

  await openEventManagement(page);
  const rallyBuilder = page.locator('section[aria-label="Rally roster checks"]');
  await assertAccessibleSurface(page, rallyBuilder);
  await expect(rallyBuilder.getByText('No Rally groups have been created for this occurrence.')).toBeVisible();
  actual.rallyBuilder = await screenshotHash(rallyBuilder);

  await openMemberProfile(page);
  const memberProfile = page.locator('section[aria-labelledby="member-capability-profile"]');
  await assertAccessibleSurface(page, memberProfile);
  await expect(
    memberProfile.getByRole('heading', { name: 'What we know about this Governor' }),
  ).toBeVisible();
  actual.memberProfile = await screenshotHash(memberProfile);

  await openTransferCampaign(page);
  const transferCampaign = page.locator('section[aria-labelledby="transfer-campaign-heading"]');
  await assertAccessibleSurface(page, transferCampaign);
  await expect(
    transferCampaign.getByRole('heading', { name: 'Transfer campaign workspace' }),
  ).toBeVisible();
  actual.transferCampaign = await screenshotHash(transferCampaign);

  await openIntelligenceTimeline(page);
  const intelligenceTimeline = page.locator('section[aria-labelledby="intelligence-timeline-heading"]');
  await assertAccessibleSurface(page, intelligenceTimeline);
  await expect(
    intelligenceTimeline.getByRole('heading', { name: 'Kingdom intelligence timeline' }),
  ).toBeVisible();
  actual.intelligenceTimeline = await screenshotHash(intelligenceTimeline);

  await page.goto('/dashboard');
  await settle(page);
  const allianceCommand = page.locator('section[aria-labelledby="alliance-command-heading"]');
  await assertAccessibleSurface(page, allianceCommand);
  await expect(allianceCommand.getByRole('heading', { name: 'Officer overview' })).toBeVisible();
  await expect(allianceCommand.getByText('Events', { exact: true })).toBeVisible();
  await expect(allianceCommand).not.toContainText('application.dashboard.commandOwners.');
  actual.allianceCommand = await screenshotHash(allianceCommand);
  const officerBriefs = allianceCommand
    .getByRole('heading', { name: 'Officer briefs' })
    .locator('..')
    .locator('..');
  await assertAccessibleSurface(page, officerBriefs);
  actual.officerBriefs = await screenshotHash(officerBriefs);

  await page.goto('/assistant');
  await settle(page);
  const assistant = page.locator('main');
  await assertAccessibleSurface(page, assistant);
  await expect(assistant.getByRole('heading', { name: 'Ask your Alliance', level: 1 })).toBeVisible();
  await expect(assistant.locator('[aria-live="polite"]')).toHaveCount(1);
  actual.assistant = await screenshotHash(assistant);

  expect(actual, `Update capability matrix fingerprints for ${testInfo.project.name}`).toEqual(
    fingerprints[testInfo.project.name],
  );
});
