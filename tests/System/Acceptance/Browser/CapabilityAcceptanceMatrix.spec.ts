import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { normalizeFixtureText } from '../Support/normalizeFixtureText';

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
    rallyBuilder: '92eb5061b782cd0ce1eb9582ef37c822ddf26fa4bc3040afe923e5a8d9d3cc59',
    memberProfile: '6ada038a0fa095c29f4c76075516f47ecc069fda7297c861f8f20a1ba842dc18',
    transferCampaign: '5dde5982853633b68a24ab3ff302fa2c91e790c373256243a458348da499544f',
    intelligenceTimeline: '69d11d614a5499b61ef944c5bc8b8c5f122acc0bcb0d3bc9f0ba57d0c086409a',
    allianceCommand: 'd243702288d28bbdadf85a94675bcb448ddd668ec207eed3a2cab4d56dfba9a5',
    officerBriefs: 'aa11099af0cba9022487b46a7c205882f6a55577fb906c33a19ec56584379ed6',
    assistant: 'f59e63477ac2f4fcc873ab726ba171254e5dd68f9257d467a9f64acc8794635b',
  },
  mobile: {
    rallyBuilder: '92eb5061b782cd0ce1eb9582ef37c822ddf26fa4bc3040afe923e5a8d9d3cc59',
    memberProfile: '83691d32558397533703f52cc107b0439241bce40c87b4f0e2ef9fc3dacbb81a',
    transferCampaign: '28a9a6e01cd1f22f1c9a986563b835be49e3dac7adbdc99e540e5043b6f6875e',
    intelligenceTimeline: '8154bfe0f96146fd78e990d24c45ae1e97f2d7890879b7fb2ccef1c79dd50086',
    allianceCommand: 'd243702288d28bbdadf85a94675bcb448ddd668ec207eed3a2cab4d56dfba9a5',
    officerBriefs: 'aa11099af0cba9022487b46a7c205882f6a55577fb906c33a19ec56584379ed6',
    assistant: 'f59e63477ac2f4fcc873ab726ba171254e5dd68f9257d467a9f64acc8794635b',
  },
};

async function login(page: Page): Promise<void> {
  await visit(page, '/login');
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

async function visit(page: Page, path: string): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status(), `Navigation to ${path} must succeed`).toBe(200);
  await settle(page);
}

async function follow(page: Page, link: Locator): Promise<void> {
  const href = await link.getAttribute('href');
  if (!href) throw new Error('Acceptance navigation requires a link destination.');
  await Promise.all([page.waitForURL(new URL(href, page.url()).toString()), link.click()]);
  await settle(page);
}

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);
}

async function openEventManagement(page: Page): Promise<void> {
  await visit(page, '/events');
  await follow(page, page.getByRole('link', { name: 'Capability Acceptance Bear Hunt' }).first());
  await follow(page, page.getByRole('link', { name: 'Manage Event' }));
}

async function openMemberProfile(page: Page): Promise<void> {
  await visit(page, '/alliance/roster/intelligence');
  await follow(page, page.getByRole('link', { name: 'Acceptance Marshal' }).first());
}

async function openTransferCampaign(page: Page): Promise<void> {
  await visit(page, '/alliance/recruitment');
  await follow(page, page.getByRole('link', { name: 'Acceptance Candidate' }).first());
}

async function openIntelligenceTimeline(page: Page): Promise<void> {
  await visit(page, '/alliance/kingdom-alliances');
  await follow(page, page.getByRole('link', { name: 'Timeline Watch' }).first());
}

const requiredFacts: Record<Surface, string[]> = {
  rallyBuilder: ['Capability Acceptance Bear Hunt', '4 blockers', 'Participation', 'Rally'],
  memberProfile: [
    'Acceptance Marshal',
    'Current',
    '145,000,000',
    '120,000,000',
    '+25,000,000',
    'TC3',
    'TC2',
  ],
  transferCampaign: [
    'Acceptance Candidate',
    'screening',
    'Linked to a Governor',
    'Not assessed',
    'No Transfer participant is recorded',
    'Officer reason',
    'New officer note',
  ],
  intelligenceTimeline: ['Timeline Watch', '125000000', '100000000', '54', '50', 'Scout history'],
  allianceCommand: [
    'Officer overview',
    '4 factual items need attention',
    '4 blockers',
    '2 recent factual Intelligence changes',
    'Governor observations are current',
  ],
  officerBriefs: [
    'Daily Officer Brief',
    'Upcoming Event Brief',
    'Post-Event Closeout Brief',
    '3 source facts',
    '1 source facts',
    '0 source facts',
  ],
  assistant: [
    'Ask your Alliance',
    'What needs officer attention?',
    'Which Governor observations are stale or missing?',
  ],
};

async function fingerprint(target: Locator, surface: Surface): Promise<string> {
  await expect(target).toBeVisible();
  const raw = await target.innerText();
  for (const fact of requiredFacts[surface]) {
    expect(raw.toLowerCase(), `${surface} must retain ${fact}`).toContain(fact.toLowerCase());
  }
  expect(raw, `${surface} must not render unresolved localization keys`).not.toMatch(
    /\b(?:recruitment|commandOverview|officerBriefs)\.[a-zA-Z]/i,
  );
  const { text, dateCount } = normalizeFixtureText(raw);
  if (surface !== 'officerBriefs' && surface !== 'assistant') {
    expect(dateCount, `${surface} must retain valid rendered fixture dates`).toBeGreaterThan(0);
  }
  const hash = createHash('sha256').update(text).digest('hex');
  if (hash !== fingerprints[test.info().project.name][surface]) {
    await test.info().attach(`${surface}-rendered`, {
      body: await target.screenshot({ animations: 'disabled', caret: 'hide' }),
      contentType: 'image/png',
    });
  }
  await test.info().attach(`${surface}-raw-text`, { body: raw, contentType: 'text/plain' });
  await test.info().attach(`${surface}-text`, { body: text, contentType: 'text/plain' });
  return hash;
}

async function captureSurface(page: Page, surface: Surface): Promise<string> {
  switch (surface) {
    case 'rallyBuilder':
      await openEventManagement(page);
      return fingerprint(page.locator('main'), surface);
    case 'memberProfile':
      await openMemberProfile(page);
      return fingerprint(page.locator('main'), surface);
    case 'transferCampaign':
      await openTransferCampaign(page);
      return fingerprint(page.locator('main'), surface);
    case 'intelligenceTimeline':
      await openIntelligenceTimeline(page);
      return fingerprint(page.locator('main'), surface);
    case 'allianceCommand':
      await visit(page, '/dashboard');
      return fingerprint(
        page.getByRole('region', { name: 'Officer overview', exact: true }),
        surface,
      );
    case 'officerBriefs':
      await visit(page, '/dashboard');
      return fingerprint(
        page
          .getByRole('region', { name: 'Officer overview', exact: true })
          .locator(':scope > div')
          .filter({ has: page.getByRole('heading', { name: 'Officer briefs', exact: true }) }),
        surface,
      );
    case 'assistant':
      await visit(page, '/assistant');
      return fingerprint(page.locator('main'), surface);
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

test('capability acceptance surfaces remain visually and semantically stable', async ({
  page,
}, testInfo) => {
  // This acceptance case intentionally traverses seven authenticated surfaces. The
  // default 30s Playwright budget is suitable for focused cases but made this
  // aggregate matrix flaky under CI load, so give only this test a realistic cap.
  test.setTimeout(90_000);

  await login(page);

  const viewport = testInfo.project.name;
  const actual = {} as Record<Surface, string>;
  for (const surface of surfaces) {
    actual[surface] = await test.step(surface, () => captureSurface(page, surface));
  }

  expect(actual, `Update capability matrix fingerprints for ${viewport}`).toEqual(
    fingerprints[viewport],
  );
});
