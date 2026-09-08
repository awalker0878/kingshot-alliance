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
    rallyBuilder: 'c469721788f54d5c21c8a91deaee742340d9d64a96204cc757a6f82101253d8d',
    memberProfile: '55d91b84c52af11c7535843c5643f522c917a0ab138697e8da7e91102c978be3',
    transferCampaign: 'c882ea931c07d53c82830fbeecbabb29673e2f388e17ccf21a78636eed38654c',
    intelligenceTimeline: '6eb2cb411808b90ef39ff7224d38514ee6dbe394a8187ef576254a0c0774cd1e',
    allianceCommand: '0f4da284a29f90f42bfa3cf4db1d11100565d7669b02a0965993578facf200c6',
    officerBriefs: '32625fa3bb1c92568eac9f2b8cc4994439ab05218e41c308e94045e481cbe209',
    assistant: '8e846a2c36a8616ce1862b4aa304e782d4e0ae4e3eaf5b1a6776a13504487c5d',
  },
  mobile: {
    rallyBuilder: 'f4308b63a7aaddc01198c9a031991d4f23e8fadd9949a9cfc6bec46925f3c693',
    memberProfile: '2b6a8ada865dec79dba58863197e4baeb98a1a758e6d81807da04c568f900560',
    transferCampaign: 'e921c31301627dc71b87c97e8d7c883f6e31394dc788ca349c196eaa2d13605d',
    intelligenceTimeline: 'dd76b380e395deee7662c44879c0ee7b794ea0eb6d6cf5aefbee4c68db55b0ff',
    allianceCommand: '8691b3ae207fcacd252acb45bc1b8ad0f7549bcf863ff33bb34a04a24d67829d',
    officerBriefs: '3ba052ab7c02ad6806868e1e166bbb8283f62e517ae9fbc9220cedc646e7fb91',
    assistant: '5a5b5a4ab893debea3d19e9deb01f4326dd5a0df8829b16c814ba750af7db25a',
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
