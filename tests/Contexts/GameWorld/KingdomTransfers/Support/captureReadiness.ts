import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { assertViewportRaster, viewportCapturePlan } from './viewportRaster';

/** Capture every main-content pixel through actual painted viewports, never an oversized full-page texture. */
export async function captureReadiness(page: Page): Promise<string> {
  const viewport = page.viewportSize();
  if (!viewport) throw new Error('Readiness verification requires the configured viewport.');
  await page.evaluate(() => window.scrollTo({ left: 0, top: 0, behavior: 'instant' }));
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
  // The desktop bar is a div, not a header. Inspect actual positioned overlays
  // intersecting main; the fixed sidebar does not intersect its horizontal span.
  const inset = await main.evaluate((element) => {
    const main = element.getBoundingClientRect();
    let bottom = 0;
    for (const overlay of document.querySelectorAll('body *')) {
      const style = getComputedStyle(overlay);
      if (!['sticky', 'fixed'].includes(style.position)) continue;
      const rect = overlay.getBoundingClientRect();
      if (rect.top <= 0 && rect.bottom > 0 && rect.right > main.left && rect.left < main.right)
        bottom = Math.max(bottom, rect.bottom);
    }
    return Math.ceil(bottom);
  });
  const { shellPrefix, tiles } = viewportCapturePlan(rect.top, rect.height, viewport.height, inset);
  const captures: { top: number; height: number; sha256: string }[] = [];
  for (const [index, tile] of tiles.entries()) {
    const y = await page.evaluate(
      async ({ top, inset }) => {
        // CSS scroll-behavior is smooth; a capture must not race an in-flight scroll.
        window.scrollTo({ left: 0, top: top - inset, behavior: 'instant' });
        await new Promise<void>((resolve) =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
        );
        return top - window.scrollY;
      },
      { top: tile.top, inset },
    );
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
    await test.info().attach(`readiness-tile-${String(index + 1).padStart(2, '0')}`, {
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
  const manifest = { viewport, shell, rect, inset, shellPrefix, tiles: captures };
  await test.info().attach('readiness-capture-coverage', {
    body: JSON.stringify(manifest, null, 2),
    contentType: 'application/json',
  });
  return createHash('sha256').update(JSON.stringify(manifest)).digest('hex');
}
