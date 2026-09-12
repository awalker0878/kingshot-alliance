/** Evict clean off-page rows, retain intentional unsaved edits, and refresh clean visible rows. */
export function reconcileContentDrafts<T>(
  current: Record<string, T>,
  baseline: Record<string, T>,
  incoming: Record<string, T>,
): { drafts: Record<string, T>; baseline: Record<string, T> } {
  const drafts: Record<string, T> = {};
  const nextBaseline: Record<string, T> = {};
  for (const [id, value] of Object.entries(current)) {
    if (JSON.stringify(value) !== JSON.stringify(baseline[id])) {
      drafts[id] = value;
      const prior = baseline[id];
      if (prior !== undefined) nextBaseline[id] = prior;
    }
  }
  for (const [id, value] of Object.entries(incoming)) {
    if (!(id in drafts)) {
      drafts[id] = structuredClone(value);
      nextBaseline[id] = structuredClone(value);
    }
  }
  return { drafts, baseline: nextBaseline };
}

/** Apply a successful save without discarding edits typed after that request started. */
export function acceptContentDraft<T>(
  current: Record<string, T>,
  baseline: Record<string, T>,
  id: string,
  submitted: T,
  canonical: T | undefined,
): void {
  if (JSON.stringify(current[id]) === JSON.stringify(submitted)) {
    if (canonical === undefined) {
      delete current[id];
      delete baseline[id];
      return;
    }
    current[id] = structuredClone(canonical);
  }
  baseline[id] = structuredClone(canonical ?? submitted);
}
