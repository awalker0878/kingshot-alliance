import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import {
  TransferChoiceLoader,
  parseTransferChoices,
} from '../../../../resources/js/components/transfers/transferChoices.ts';
import type {
  TransferChoiceRequest,
  TransferChoiceView,
} from '../../../../resources/js/components/transfers/transferChoices.ts';

const id = (index: number) => '01ARZ3NDEKTSV4RRFFQ69G5' + String(index).padStart(3, '0');
function result(start = 1, count = 25, next: string | null = 'next-token', selected = 60) {
  return {
    page: {
      items: Array.from({ length: count }, (_, index) => ({
        id: id(start + index),
        name: `Choice ${start + index}`,
      })),
      nextCursor: next,
      hasMore: next !== null,
      pageSize: 25,
      isFirstPage: start === 1,
    },
    total: 65,
    selected: selected === 0 ? null : { id: id(selected), name: `Choice ${selected}` },
  };
}
const request: TransferChoiceRequest = {
  scope: 'actor|alliance|plan',
  kind: 'roster',
  planId: id(90),
  search: '',
  cursor: null,
  selectedId: id(60),
};
const response = (value: unknown) =>
  new Response(JSON.stringify(value), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => {
    resolve = done;
  });
  return { promise, resolve };
}

test('bounded pages retain an independently selected off-page identity', () => {
  assert.deepEqual(parseTransferChoices(result(), id(60)), result());
  assert.deepEqual(parseTransferChoices(result(1, 0, null, 0), null), result(1, 0, null, 0));
  assert.deepEqual(parseTransferChoices(result(51, 15, null), id(60)), result(51, 15, null));
});

test('malformed, oversized or duplicate option responses fail closed', () => {
  for (const value of [
    null,
    {},
    { page: [] },
    result(1, 26),
    { ...result(), total: -1 },
    { ...result(), total: 2.5 },
    { ...result(), selected: { id: id(9), name: 'wrong requested identity' } },
    { ...result(), page: { ...result().page, items: [{ id: 'invalid', name: 'bad' }] } },
    {
      ...result(),
      page: { ...result().page, items: [result().page.items[0], result().page.items[0]] },
    },
    { ...result(), page: { ...result().page, hasMore: false } },
    { ...result(), page: { ...result().page, isFirstPage: undefined } },
    { ...result(), page: { ...result().page, nextCursor: '' } },
    { ...result(), page: { ...result().page, pageSize: 1000 } },
  ])
    assert.throws(() => parseTransferChoices(value, id(60)), /Invalid transfer choices/);
});

test('requests preserve the current exact plan, search and selected values without changing selection', async () => {
  let view: TransferChoiceView | undefined;
  let captured: URL | undefined;
  const loader = new TransferChoiceLoader(
    async (url, init) => {
      captured = new URL(url, 'https://example.invalid');
      assert.equal(init.credentials, 'same-origin');
      assert.deepEqual(init.headers, { Accept: 'application/json' });
      return response(result());
    },
    (next) => {
      view = next;
    },
  );
  await loader.load({ ...request, search: '% & ? value' });
  assert.equal(captured?.pathname, '/alliance/transfers/manage/choices/roster');
  assert.equal(captured?.searchParams.get('plan'), id(90));
  assert.equal(captured?.searchParams.get('selected'), id(60));
  assert.equal(captured?.searchParams.get('q'), '% & ? value');
  assert.equal(view?.result?.selected?.id, id(60));
  assert.equal(view?.busy, false);
  assert.equal(view?.failed, false);
});

for (const failure of ['network', 'http', 'json', 'shape']) {
  test(`a ${failure} failure preserves the successful page and retries the same cursor`, async () => {
    let calls = 0;
    let view: TransferChoiceView | undefined;
    const urls: string[] = [];
    const loader = new TransferChoiceLoader(
      async (url) => {
        urls.push(url);
        calls++;
        if (calls === 1) return response(result());
        if (calls === 2) {
          if (failure === 'network') throw new Error('offline');
          if (failure === 'http') return new Response('', { status: 503 });
          if (failure === 'json') return new Response('{invalid', { status: 200 });
          return response({ page: { items: [] } });
        }
        return response(result(26));
      },
      (next) => {
        view = next;
      },
    );
    await loader.load(request);
    const previous = view?.result;
    await loader.load({ ...request, cursor: 'continuation', search: 'kept search' });
    assert.equal(view?.result, previous);
    assert.equal(view?.failed, true);
    assert.equal(view?.busy, false);
    await loader.retry();
    assert.equal(urls[1], urls[2]);
    assert.equal(view?.result?.page.items[0]?.id, id(26));
    assert.equal(view?.cursor, 'continuation');
    assert.equal(view?.search, 'kept search');
    assert.equal(view?.failed, false);
  });
}

test('a superseded search cannot overwrite the current response even if abort is ignored', async () => {
  const old = deferred<Response>();
  let calls = 0;
  let view: TransferChoiceView | undefined;
  let signal: AbortSignal | undefined;
  const loader = new TransferChoiceLoader(
    async (_, init) => {
      if (++calls === 1) {
        signal = init.signal as AbortSignal;
        return old.promise;
      }
      return response(result(26));
    },
    (next) => {
      view = next;
    },
  );
  const pending = loader.load(request);
  await loader.load({ ...request, search: 'new search' });
  assert.equal(signal?.aborted, true);
  old.resolve(response(result()));
  await pending;
  assert.equal(view?.search, 'new search');
  assert.equal(view?.result?.page.items[0]?.id, id(26));
});

test('switching principal, tenant or plan clears old data before another request can complete', async () => {
  for (const newScope of ['different actor', 'different alliance', 'different plan']) {
    let view: TransferChoiceView | undefined;
    let calls = 0;
    const delayed = deferred<Response>();
    const loader = new TransferChoiceLoader(
      async () => (++calls === 1 ? response(result()) : delayed.promise),
      (next) => {
        view = next;
      },
    );
    await loader.load(request);
    const pending = loader.load({ ...request, cursor: 'old-page' });
    loader.reset(newScope);
    assert.equal(view?.result, null);
    assert.equal(view?.busy, false);
    delayed.resolve(response(result(26)));
    await pending;
    assert.equal(view?.result, null);
    await loader.retry();
    assert.equal(calls, 2, 'Old-scope requests are not retained as a retry target.');
  }
});

test('successful page transitions retain one page, not a growing option cache', async () => {
  let page = 0;
  let view: TransferChoiceView | undefined;
  const loader = new TransferChoiceLoader(
    async () => response(result(++page)),
    (next) => {
      view = next;
    },
  );
  for (let i = 0; i < 100; i++) {
    await loader.load({ ...request, cursor: i ? `cursor-${i}` : null });
    assert.equal(view?.result?.page.items.length, 25);
  }
  assert.equal(view?.result?.page.items[0]?.id, id(100));
});

test('every supported locale contains the choice navigation and failure labels', async () => {
  const root = new URL(
    '../../../../resources/js/localization/messages/transfers/',
    import.meta.url,
  );
  const locales = readdirSync(root).filter((name) => name.endsWith('.ts'));
  assert.equal(locales.length, 17);
  for (const locale of locales) {
    const { default: messages } = await import(new URL(locale, root).href);
    for (const key of [
      'searchChoices',
      'choicePageSummary',
      'choiceUnavailable',
      'choicesFailed',
      'retryChoices',
    ]) {
      assert.equal(typeof messages.kingdomP7D[key], 'string', `${locale} ${key}`);
      assert.ok(messages.kingdomP7D[key].length > 0);
    }
    assert.match(messages.kingdomP7D.choicePageSummary, /\{count\}/);
    assert.match(messages.kingdomP7D.choicePageSummary, /\{total\}/);
  }
});

test('management consumes lazy selectors without keeping the superseded audience props', () => {
  const page = readFileSync(
    new URL('../../../../resources/js/pages/Kingdom/Transfer/Manage.vue', import.meta.url),
    'utf8',
  );
  assert.ok(page.includes('TransferChoicePicker'));
  assert.ok(!page.includes('rosterOptions'));
  assert.ok(!page.includes('players: PlayerOption[]'));
  const component = readFileSync(
    new URL(
      '../../../../resources/js/components/transfers/TransferChoicePicker.vue',
      import.meta.url,
    ),
    'utf8',
  );
  assert.ok(component.includes('loader.retry()'));
  assert.ok(component.includes('loader.reset()'));
  assert.ok(
    !component.includes("emit('update:modelValue', '')"),
    'Read failure cannot clear a user draft.',
  );
});

test('cohort requests bind participant identity and discard an old participant response', async () => {
  const old = deferred<Response>();
  const urls: string[] = [];
  let view: TransferChoiceView | undefined;
  const loader = new TransferChoiceLoader(
    async (url) => {
      urls.push(url);
      return urls.length === 1 ? old.promise : response(result());
    },
    (next) => {
      view = next;
    },
  );
  const first = loader.load({ ...request, kind: 'cohorts', participantId: id(91) });
  await loader.load({ ...request, kind: 'cohorts', participantId: id(92) });
  assert.equal(new URL(urls[1]!, 'https://example.test').searchParams.get('participant'), id(92));
  old.resolve(response(result(51, 15, null)));
  await first;
  assert.equal(view?.result?.page.items[0]?.id, id(1));
});
