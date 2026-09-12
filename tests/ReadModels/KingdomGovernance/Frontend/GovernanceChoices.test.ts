import assert from 'node:assert/strict';
import test from 'node:test';
import { governanceCatalogueLabels } from '../../../../resources/js/localization/governance-catalogue-labels.ts';
import {
  GovernanceChoiceLoader,
  parseGovernanceChoices,
} from '../../../../resources/js/components/governance/governanceChoices.ts';
import type {
  GovernanceChoiceRequest,
  GovernanceChoiceView,
} from '../../../../resources/js/components/governance/governanceChoices.ts';

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
const request: GovernanceChoiceRequest = {
  scope: 'actor|kingdom',
  kind: 'players',
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
  assert.deepEqual(parseGovernanceChoices(result(), id(60)), result());
  assert.deepEqual(parseGovernanceChoices(result(1, 0, null, 0), null), result(1, 0, null, 0));
  assert.deepEqual(parseGovernanceChoices(result(51, 15, null), id(60)), result(51, 15, null));
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
    assert.throws(() => parseGovernanceChoices(value, id(60)), /Invalid Governance choices/);
});

test('requests preserve the current exact Kingdom, search and selected values without changing selection', async () => {
  let view: GovernanceChoiceView | undefined;
  let captured: URL | undefined;
  const loader = new GovernanceChoiceLoader(
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
  assert.equal(captured?.pathname, '/alliance/settings/kingdom/governance/choices/players');
  assert.equal(captured?.searchParams.get('selected_id'), id(60));
  assert.equal(captured?.searchParams.get('search'), '% & ? value');
  assert.equal(view?.result?.selected?.id, id(60));
  assert.equal(view?.busy, false);
  assert.equal(view?.failed, false);
});

for (const failure of ['network', 'http', 'json', 'shape']) {
  test(`a ${failure} failure preserves the successful page and retries the same cursor`, async () => {
    let calls = 0;
    let view: GovernanceChoiceView | undefined;
    const urls: string[] = [];
    const loader = new GovernanceChoiceLoader(
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
  let view: GovernanceChoiceView | undefined;
  let signal: AbortSignal | undefined;
  const loader = new GovernanceChoiceLoader(
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

test('switching principal or Kingdom clears old data before another request can complete', async () => {
  for (const newScope of ['different actor', 'different Kingdom']) {
    let view: GovernanceChoiceView | undefined;
    let calls = 0;
    const delayed = deferred<Response>();
    const loader = new GovernanceChoiceLoader(
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
  let view: GovernanceChoiceView | undefined;
  const loader = new GovernanceChoiceLoader(
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

test('every supported locale contains choice navigation and failure labels', () => {
  assert.equal(Object.keys(governanceCatalogueLabels).length, 17);
  for (const [locale, messages] of Object.entries(governanceCatalogueLabels)) {
    for (const key of [
      'searchChoices',
      'choicePageSummary',
      'choiceUnavailable',
      'choicesFailed',
      'retryChoices',
      'retryPage',
    ]) {
      assert.equal(typeof messages[key], 'string', `${locale} ${key}`);
      assert.ok(messages[key]!.length > 0);
    }
    assert.match(messages.choicePageSummary!, /\{count\}/);
    assert.match(messages.choicePageSummary!, /\{total\}/);
  }
});

test('complete Unicode display names fit the owner name and game identifier bounds', () => {
  const value = result();
  value.page.items[0]!.name = '界'.repeat(160) + ' · ' + '🧭'.repeat(100);
  assert.deepEqual(parseGovernanceChoices(value, id(60)), value);
  value.page.items[0]!.name += 'x';
  assert.throws(() => parseGovernanceChoices(value, id(60)));
});
