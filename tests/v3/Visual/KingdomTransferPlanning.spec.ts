import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const transferVisualFingerprints: Record<string, string> = {
  desktop: 'aaf55590a0019815680596f02a1c00a382ac19f9265e69ca9beeb604d65e9b71',
  mobile: 'a8121ae901d149acfa90dedf594c979d2903755c3fc5312871ddbfc56970dc81',
};

async function openTransferPlanning(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('transfer-visual@example.test');
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

  await page.goto('/alliance/transfers/readiness');
  await page.waitForLoadState('networkidle');
}

test('Kingdom Transfer Planning keeps eligibility, verification, readiness, and reviewed evidence distinct', async ({
  page,
}, testInfo) => {
  await openTransferPlanning(page);
  await page.evaluate(() => document.fonts.ready);

  const northstarHeading = page.getByRole('heading', { name: 'Northstar Marshal', level: 2 });
  const emberHeading = page.getByRole('heading', { name: 'Ember Vanguard', level: 2 });
  const frostHeading = page.getByRole('heading', { name: 'Frost Envoy', level: 2 });
  const northstarCard = page.locator('article').filter({ has: northstarHeading });
  const emberCard = page.locator('article').filter({ has: emberHeading });
  const frostCard = page.locator('article').filter({ has: frostHeading });

  await expect(northstarCard).toBeVisible();
  await expect(emberCard).toBeVisible();
  await expect(frostCard).toBeVisible();
  await expect(page.getByText(/kingdomP7D\./)).toHaveCount(0);
  await expect(northstarCard.getByText('Eligible now', { exact: true })).toBeVisible();
  await expect(emberCard.getByText('Blocked', { exact: true }).first()).toBeVisible();
  await expect(frostCard.getByText('Needs verification', { exact: true }).first()).toBeVisible();
  await expect(northstarCard.getByText('Transfer Group 7', { exact: true })).toBeVisible();
  await expect(northstarCard).toContainText('K1524 Vanguard');
  await expect(
    northstarCard.getByText('Confirm alliance hand-off time', { exact: true }),
  ).toBeVisible();

  const filter = page.getByRole('combobox', { name: /eligibility/i });
  await expect(filter).toBeVisible();
  await expect(filter).toHaveAccessibleName(/eligibility/i);
  await filter.focus();
  await page.keyboard.press('ArrowDown');
  await expect(filter).toHaveValue('eligible_now');
  await expect(northstarCard).toBeVisible();
  await expect(emberCard).toBeHidden();
  await page.keyboard.press('Home');
  await expect(filter).toHaveValue('all');
  await expect(emberCard).toBeVisible();

  const evidenceDetails = northstarCard
    .locator('details')
    .filter({ hasText: 'Add in-game evidence' });
  await evidenceDetails.locator('summary').click();
  await expect(evidenceDetails.getByText('Upload and classify', { exact: true })).toBeVisible();
  if (!(await evidenceDetails.evaluate((element) => (element as HTMLDetailsElement).open))) {
    await evidenceDetails.locator('summary').click();
  }
  await expect(evidenceDetails).toHaveAttribute('open', '');

  const governorStatusEvidence = evidenceDetails
    .getByRole('heading', { name: 'Transfer Governor status', exact: true, level: 4 })
    .locator('xpath=ancestor::article[1]');
  const scoreEvidence = evidenceDetails
    .getByRole('heading', { name: 'Transfer Score / Passes', exact: true, level: 4 })
    .locator('xpath=ancestor::article[1]');
  const committedInvitation = evidenceDetails
    .getByRole('heading', { name: 'Transfer invitation', exact: true, level: 4 })
    .locator('xpath=ancestor::article[1]');

  await expect(governorStatusEvidence).toBeVisible();
  await expect(scoreEvidence).toBeVisible();
  await expect(committedInvitation).toBeVisible();

  const screenshotClass = evidenceDetails.getByRole('combobox', { name: 'Screenshot class' });
  await expect(screenshotClass).toBeVisible();
  await expect(screenshotClass).toHaveValue('transfer_governor_status');
  await expect(screenshotClass.locator('option')).toHaveText([
    'Transfer Governor status',
    'Transfer Score / Passes',
    'Transfer invitation',
    'Target Kingdom transfer rules',
    'Official Transfer Group',
  ]);

  await expect(
    governorStatusEvidence.getByText('Possible visual duplicate', { exact: true }),
  ).toBeVisible();
  await expect(
    governorStatusEvidence
      .locator('p:visible', {
        hasText: /^Below the schema confidence requirement — verify carefully\.$/,
      })
      .first(),
  ).toBeVisible();
  await expect(governorStatusEvidence).toContainText('Raw observation');
  await expect(governorStatusEvidence).toContainText('Normalized candidate');

  await expect(scoreEvidence).toContainText('Evidence status: Approved');
  await scoreEvidence
    .getByRole('button', { name: 'Preview destination facts and eligibility impact' })
    .click();
  await expect(scoreEvidence.getByText('Before commit', { exact: true })).toBeVisible();
  await expect(scoreEvidence.getByText('After reviewed facts', { exact: true })).toBeVisible();
  await expect(scoreEvidence).toContainText(
    'Facts this screenshot would add: transfer_score, transfer_passes_available, transfer_passes_required',
  );
  await expect(scoreEvidence).not.toContainText('in_game_rules_verified');

  await expect(committedInvitation.getByText('Succeeded', { exact: true })).toBeVisible();
  await expect(committedInvitation).toContainText('Destination receipt');

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBeFalsy();

  await page
    .locator('p')
    .filter({ hasText: /^Evaluated/ })
    .evaluateAll((elements) =>
      elements.forEach((element) => (element.textContent = 'Evaluated at fixture time')),
    );
  await page
    .getByText(/Destination receipt/)
    .evaluateAll((elements) =>
      elements.forEach(
        (element) => (element.textContent = 'Succeeded · Destination receipt fixture'),
      ),
    );

  const screenshot = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    fullPage: true,
    path: testInfo.outputPath('kingdom-transfer-planning.png'),
    scale: 'css',
  });
  const actualFingerprint = createHash('sha256').update(screenshot).digest('hex');
  const expectedFingerprint = transferVisualFingerprints[testInfo.project.name];

  expect(
    actualFingerprint,
    `Update Kingdom Transfer Planning visual fingerprint for ${testInfo.project.name}`,
  ).toBe(expectedFingerprint);
});

test('Transfer observation history pages independently and retries a failed continuation', async ({
  page,
}) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await openTransferPlanning(page);
  const card = page.locator('article').filter({
    has: page.getByRole('heading', { name: 'Northstar Marshal', exact: true, level: 2 }),
  });
  const history = card
    .locator('details')
    .filter({ has: page.locator('summary', { hasText: /^Observation history$/ }) });
  await history.locator('summary').click();
  await expect(history.locator('li')).toHaveCount(25);
  const first = await history.locator('li').allTextContents();
  const seen = [...first];
  await page.route('**/observations?cursor=*', (route) =>
    route.fulfill({ status: 503, body: '{}' }),
  );
  await history.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(history.getByRole('alert')).toContainText(
    'Observation history could not be loaded.',
  );
  await page.unroute('**/observations?cursor=*');
  await history.getByRole('button', { name: 'Reload history', exact: true }).click();
  await expect(history.locator('li')).toHaveCount(25);
  seen.push(...(await history.locator('li').allTextContents()));
  await history.getByRole('button', { name: 'Next page', exact: true }).click();
  await expect(history.getByRole('button', { name: 'Next page', exact: true })).toHaveCount(0);
  seen.push(...(await history.locator('li').allTextContents()));
  expect(seen.filter((text) => text.includes('Historical transfer score'))).toHaveLength(55);
  expect(new Set(seen).size).toBe(seen.length);
  await history.getByRole('button', { name: 'First page', exact: true }).click();
  await expect(history.locator('li')).toHaveText(first);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    ),
  ).toBe(false);
  expect(errors).toEqual([]);
});
