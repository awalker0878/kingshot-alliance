/** Wait for consecutive identical frames, without access to any expected baseline. */
export async function stableViewportRaster(capture: () => Promise<Buffer>): Promise<Buffer> {
  let previous: Buffer | undefined;
  // Bounded image stabilization, not a retry of the journey or its assertions.
  for (let frame = 0; frame < 6; frame++) {
    const current = await capture();
    if (previous?.equals(current)) return current;
    previous = current;
  }
  throw new Error('Readiness viewport did not produce two consecutive identical rasters.');
}
