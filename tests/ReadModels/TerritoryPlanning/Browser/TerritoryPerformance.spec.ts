import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const MIB = 1024 * 1024;

async function activateTerritoryGovernor(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#email').fill('territory-visual@example.test');
  await page.locator('#password').fill('password');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/dashboard');

  const identitySwitcher = page.locator('button[aria-haspopup="listbox"]:visible').first();
  if (await identitySwitcher.isVisible()) {
    const label = (await identitySwitcher.textContent()) ?? '';
    if (/select governor/i.test(label)) {
      await identitySwitcher.click();
      await page.getByRole('listbox', { name: 'Active Governor' }).getByRole('option').first().click();
      await page.waitForURL('**/dashboard');
    }
  }
}

function percentile(values: number[], percentileValue: number): number {
  const sorted = [...values].sort((left, right) => left - right);
  const index = Math.min(sorted.length - 1, Math.ceil(sorted.length * percentileValue) - 1);
  return sorted[Math.max(0, index)] ?? Number.POSITIVE_INFINITY;
}

test('KM-17 complete reference scene stays inside interaction, transfer and heap budgets', async ({ page }, testInfo) => {
  await activateTerritoryGovernor(page);
  await page.goto('/territory/explore');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);

  const canvas = page.locator('canvas[tabindex="0"]').first();
  await expect(canvas).toBeVisible();

  const entityText = await page.getByText(/map objects$/).first().textContent();
  const entityCount = Number((entityText ?? '').replace(/[^0-9]/g, ''));
  expect(entityCount, 'released reference corpus must be materially dense').toBeGreaterThanOrEqual(9000);

  const navigationTransfer = await page.evaluate(() => {
    const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
    return navigation?.transferSize ?? 0;
  });
  if (navigationTransfer > 0)
    expect(navigationTransfer, 'map-specific initial transfer budget').toBeLessThanOrEqual(1.5 * MIB);

  const frameSamples = await page.evaluate(async () => {
    const samples: number[] = [];
    let previous = performance.now();
    for (let index = 0; index < 48; index += 1) {
      const now = await new Promise<number>((resolve) => requestAnimationFrame(resolve));
      if (index > 3) samples.push(now - previous);
      previous = now;
    }
    return samples;
  });
  const frameP95 = percentile(frameSamples, 0.95);
  const frameBudget = testInfo.project.name === 'desktop' ? 18.5 : 35;
  expect(frameP95, `interactive frame p95 ${frameP95.toFixed(2)}ms`).toBeLessThanOrEqual(frameBudget);

  const pointerSamples = await canvas.evaluate(async (element) => {
    const samples: number[] = [];
    const target = element as HTMLElement;
    for (let index = 0; index < 24; index += 1) {
      const started = performance.now();
      target.dispatchEvent(new KeyboardEvent('keydown', { key: index % 2 ? 'ArrowLeft' : 'ArrowRight', bubbles: true }));
      await new Promise<number>((resolve) => requestAnimationFrame(resolve));
      samples.push(performance.now() - started);
    }
    return samples;
  });
  const pointerP95 = percentile(pointerSamples, 0.95);
  const pointerBudget = testInfo.project.name === 'desktop' ? 50 : 100;
  expect(pointerP95, `pointer-to-preview p95 ${pointerP95.toFixed(2)}ms`).toBeLessThanOrEqual(pointerBudget);

  const session = await page.context().newCDPSession(page);
  await session.send('Performance.enable');
  const metrics = await session.send('Performance.getMetrics');
  const heapUsed = metrics.metrics.find((metric) => metric.name === 'JSHeapUsedSize')?.value ?? 0;
  await session.detach();
  expect(heapUsed, 'JS heap metric should be available').toBeGreaterThan(0);
  const heapBudget = testInfo.project.name === 'desktop' ? 160 * MIB : 96 * MIB;
  expect(heapUsed, `workspace JS heap ${Math.round(heapUsed / MIB)} MiB`).toBeLessThanOrEqual(heapBudget);

  const cachedPrivateUrls = await page.evaluate(async () => {
    const privatePath = /\/territory(?:\/|$)|workspace-views|reconciliation|share/i;
    const hits: string[] = [];
    for (const name of await caches.keys()) {
      const cache = await caches.open(name);
      for (const request of await cache.keys()) {
        const url = new URL(request.url);
        if (privatePath.test(url.pathname)) hits.push(request.url);
      }
    }
    return hits;
  });
  expect(cachedPrivateUrls, 'private territory responses must not enter public caches').toEqual([]);
});
