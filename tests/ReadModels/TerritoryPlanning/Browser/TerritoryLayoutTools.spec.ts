import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const fixedTime = new Date('2026-09-15T12:00:00Z');

async function login(page: Page): Promise<void> {
  await page.clock.setFixedTime(fixedTime);
  await page.goto('/login');
  await page.locator('#email').fill('territory-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');

  const switcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (await switcher.isVisible()) {
    const label = (await switcher.textContent()) ?? '';
    if (/select governor/i.test(label)) {
      await switcher.click();
      await page.getByRole('listbox', { name: 'Active Governor' }).getByRole('option').first().click();
      await page.waitForURL('**/dashboard');
    }
  }
}

test('Layout Tools closes atomic editing, CSV, annotation and Hive-template operator gaps', async ({
  page,
}) => {
  await login(page);
  await page.goto('/territory');
  await page.waitForLoadState('networkidle');
  await expect(page.getByText('Bear Hive Alpha')).toBeVisible();
  await page.getByRole('link', { name: 'Layout tools' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Layout Tools' })).toBeVisible();

  await page.getByLabel('Select HQ').check();
  await page.getByLabel('Select North Star').check();
  await page.getByRole('button', { name: 'Align top' }).click();
  await expect(page.getByText(/Updated 2 object\(s\) atomically/)).toBeVisible();

  await page.getByRole('button', { name: 'Select editable' }).click();
  await page.getByRole('button', { name: 'Distribute X' }).click();
  await expect(page.getByText(/Updated 4 object\(s\) atomically/)).toBeVisible();

  const csv = [
    'key,type,variant_key,x,y,rotation,alliance_key,group_key,player_id,external_player_name,label,slot_state,external_identity_key',
    'banner,banner,default,109,100,0,owner,,,,Banner,,',
    '',
  ].join('\n');
  await page.getByLabel('CSV preview').fill(csv);
  await page.getByRole('button', { name: 'Validate CSV' }).click();
  await expect(page.getByText('Validated 1 CSV coordinate row(s).')).toBeVisible();
  await page.getByRole('button', { name: 'Apply coordinates' }).click();
  await expect(page.getByText(/Updated 1 object\(s\) atomically/)).toBeVisible();
  await expect(page.getByText('Unsaved layout changes')).toBeVisible();

  await page.getByRole('button', { name: 'Add annotation' }).click();
  const annotation = page.getByRole('group', { name: 'Annotation 1' });
  await expect(annotation).toBeVisible();
  await annotation.getByLabel('Kind').selectOption('arrow');
  await annotation.getByLabel('Text').fill('Officer route');
  await expect(annotation.getByLabel('Target X')).toBeVisible();
  await expect(annotation.getByLabel('Target Y')).toBeVisible();

  await page.getByLabel('Template name').fill('KM tools browser template');
  await page.getByLabel('City count').fill('4');
  await page.getByLabel('Spacing').fill('0');
  await page.getByRole('button', { name: 'Save template' }).click();
  await expect(page.getByText(/Hive template “KM tools browser template” saved/)).toBeVisible();
  await expect(page.getByLabel('Saved template')).toHaveValue(/^[0-9A-HJKMNP-TV-Z]{26}$/i);
});
