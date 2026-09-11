import { onBeforeUnmount, ref, shallowRef } from 'vue';

/** One displayed history page per component; stale requests never replace a newer page. */
export function useContentHistory<T>(endpoint: () => string) {
  const items = shallowRef<T[]>([]);
  const total = ref(0);
  const nextCursor = ref<string | null>(null);
  const currentCursor = ref<string | null>(null);
  const busy = ref(false);
  const failed = ref(false);
  const loaded = ref(false);
  let active: AbortController | null = null;
  onBeforeUnmount(() => active?.abort());

  async function load(cursor: string | null = null): Promise<void> {
    active?.abort();
    const request = new AbortController();
    active = request;
    busy.value = true;
    failed.value = false;
    const params = new URLSearchParams();
    if (cursor) params.set('cursor', cursor);
    try {
      const response = await fetch(`${endpoint()}?${params}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: request.signal,
      });
      if (!response.ok) throw new Error('Content history could not be read.');
      const data = (await response.json()) as {
        page: { items: T[]; nextCursor: string | null; pageSize: number };
        total: number;
      };
      if (!Array.isArray(data.page.items) || data.page.items.length > data.page.pageSize)
        throw new Error('Invalid history page.');
      if (request !== active) return;
      items.value = data.page.items;
      nextCursor.value = data.page.nextCursor;
      currentCursor.value = cursor;
      total.value = data.total;
      loaded.value = true;
    } catch {
      if (!request.signal.aborted && request === active) {
        items.value = [];
        nextCursor.value = null;
        failed.value = true;
      }
    } finally {
      if (request === active) busy.value = false;
    }
  }
  return { items, total, nextCursor, currentCursor, busy, failed, loaded, load };
}
