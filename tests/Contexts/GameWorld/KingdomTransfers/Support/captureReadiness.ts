import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { assertViewportRaster, viewportTiles } from './viewportRaster';

/** Capture every main-content pixel through actual painted viewports, never an oversized full-page texture. */
export async function captureReadiness(page: Page): Promise<string> {
  const viewport = page.viewportSize();
  if (!viewport) throw new Error('Readiness verification requires the configured viewport.');
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.evaluate(
    () =>
      new Promise<void>((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
      ),
  );
  const shellImage = await page.screenshot({
    animations: 'disabled',
    caret: 'hide',
    scale: 'css',
    fullPage: false,
  });
  assertViewportRaster(shellImage, viewport.width, viewport.height);
  await test
    .info()
    .attach('readiness-shell-viewport', { body: shellImage, contentType: 'image/png' });
  const shell = createHash('sha256').update(shellImage).digest('hex');
  const main = page.locator('main');
  const rect = await main.evaluate((element) => {
    const r = element.getBoundingClientRect();
    return {
      x: Math.floor(r.x),
      top: Math.floor(r.y + window.scrollY),
      width: Math.ceil(r.right) - Math.floor(r.x),
      height: Math.ceil(r.bottom + window.scrollY) - Math.floor(r.y + window.scrollY),
    };
  });
  const inset = await page.locator('header').evaluateAll((headers) =>
    Math.ceil(
      Math.max(
        0,
        ...headers.map((header) => {
          const r = header.getBoundingClientRect();
          return ['sticky', 'fixed'].includes(getComputedStyle(header).position) &&
            r.top <= 0 &&
            r.bottom > 0
            ? r.bottom
            : 0;
        }),
      ),
    ),
  );
  const tiles = viewportTiles(rect.top, rect.height, viewport.height - inset - 1);
  const captures: { top: number; height: number; sha256: string }[] = [];
  for (const [index, tile] of tiles.entries()) {
    await page.evaluate((y) => window.scrollTo(0, y), tile.top - inset);
    await page.evaluate(
      () =>
        new Promise<void>((resolve) =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
        ),
    );
    const y = tile.top - (await page.evaluate(() => window.scrollY));
    expect(y, 'Tile must not be covered by a sticky header').toBeGreaterThanOrEqual(inset);
    expect(y + tile.height, 'Every tile must fit the actual viewport').toBeLessThanOrEqual(
      viewport.height,
    );
    const screenshot = await page.screenshot({
      animations: 'disabled',
      caret: 'hide',
      scale: 'css',
      fullPage: false,
      clip: { x: rect.x, y, width: rect.width, height: tile.height },
    });
    assertViewportRaster(screenshot, rect.width, tile.height);
    await test
      .info()
      .attach(`readiness-tile-${String(index + 1).padStart(2, '0')}`, {
        body: screenshot,
        contentType: 'image/png',
      });
    captures.push({ ...tile, sha256: createHash('sha256').update(screenshot).digest('hex') });
  }
  const end = await main.evaluate((element) => {
    const r = element.getBoundingClientRect();
    return {
      x: Math.floor(r.x),
      top: Math.floor(r.y + window.scrollY),
      width: Math.ceil(r.right) - Math.floor(r.x),
      height: Math.ceil(r.bottom + window.scrollY) - Math.floor(r.y + window.scrollY),
    };
  });
  expect(end, 'Layout must stay fixed while all pixels are captured').toEqual(rect);
  expect(page.viewportSize(), 'Do not resize or scale the tested viewport').toEqual(viewport);
  const manifest = { viewport, shell, rect, tiles: captures };
  await test
    .info()
    .attach('readiness-capture-coverage', {
      body: JSON.stringify(manifest, null, 2),
      contentType: 'application/json',
    });
  return createHash('sha256').update(JSON.stringify(manifest)).digest('hex');
}
