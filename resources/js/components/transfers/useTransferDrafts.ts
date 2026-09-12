import { reactive, watch } from 'vue';

/** Keep edited drafts, not a growing copy of every visited server page. */
export function useTransferDrafts<T extends { id: string }, D>(
  rows: () => T[],
  scope: () => string,
  defaults: (row: T) => D,
): Record<string, D> {
  const drafts = reactive({}) as Record<string, D>;
  const baselines = new Map<string, string>();
  watch(
    [scope, rows],
    ([currentScope, currentRows], previous) => {
      if (previous && previous[0] !== currentScope) {
        for (const id of Object.keys(drafts)) delete drafts[id];
        baselines.clear();
      }
      const ids = new Set(currentRows.map((row) => row.id));
      for (const id of Object.keys(drafts)) {
        if (!ids.has(id) && JSON.stringify(drafts[id]) === baselines.get(id)) {
          delete drafts[id];
          baselines.delete(id);
        }
      }
      for (const row of currentRows) {
        const next = defaults(row);
        if (!(row.id in drafts) || JSON.stringify(drafts[row.id]) === baselines.get(row.id)) {
          drafts[row.id] = next;
        }
        baselines.set(row.id, JSON.stringify(next));
      }
    },
    { immediate: true },
  );
  return drafts;
}
