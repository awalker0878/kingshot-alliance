import assert from 'node:assert/strict';
import test from 'node:test';
import {
  createEditorSession,
  TerritoryRequestError,
  type SaveReceipt,
  type SaveRequest,
} from '../../../../resources/js/features/territory-planner/engine/editor-session.ts';

type Layout = { x: number };
function setup(
  save?: (request: SaveRequest<Layout>, signal: AbortSignal) => Promise<SaveReceipt<Layout>>,
) {
  let layout = { x: 1 };
  let authority = 'governor-a';
  let counter = 0;
  const requests: SaveRequest<Layout>[] = [];
  const receipts: SaveReceipt<Layout>[] = [];
  const session = createEditorSession({
    revision: 1,
    read: () => layout,
    install: (next) => {
      layout = next;
    },
    authority: () => authority,
    mutationId: () => `mutation-${++counter}`,
    accepted: (receipt) => receipts.push(receipt),
    save: async (request, signal) => {
      requests.push(request);
      return save ? save(request, signal) : receipt(request);
    },
  });
  return {
    session,
    requests,
    receipts,
    set: (x: number) => {
      layout = { x };
    },
    get: () => layout,
    switch: () => {
      authority = 'governor-b';
    },
  };
}
function receipt(request: SaveRequest<Layout>): SaveReceipt<Layout> {
  return {
    mutation_id: request.mutation_id,
    revision: request.expected_revision + 1,
    status: 'draft',
    layout_checksum: 'a'.repeat(64),
    layout: request.layout,
  };
}
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => {
    resolve = done;
  });
  return { promise, resolve };
}

test('publication saves dirty work and uses the accepted revision/checksum', async () => {
  const f = setup();
  f.set(10);
  assert.deepEqual(await f.session.publication(), {
    expected_revision: 2,
    layout_checksum: 'a'.repeat(64),
  });
  assert.equal(f.requests[0]?.layout.x, 10);
  assert.equal(f.session.dirty(), false);
});
test('concurrent flushes share one save and later edits are serialized, not erased', async () => {
  const first = deferred<SaveReceipt<Layout>>();
  const f = setup((request) =>
    request.expected_revision === 1 ? first.promise : Promise.resolve(receipt(request)),
  );
  f.set(2);
  const a = f.session.flush();
  const b = f.session.flush();
  assert.equal(a, b);
  f.set(3);
  first.resolve(receipt(f.requests[0]!));
  await a;
  assert.deepEqual(
    f.requests.map((r) => [r.expected_revision, r.layout.x]),
    [
      [1, 2],
      [2, 3],
    ],
  );
  assert.equal(f.get().x, 3);
});
test('lost responses retry identical mutation, revision and content before newer edits', async () => {
  let fail = true;
  const f = setup(async (request) => {
    if (fail) {
      fail = false;
      throw new Error('connection lost');
    }
    return receipt(request);
  });
  f.set(2);
  await assert.rejects(f.session.flush(), /connection lost/);
  f.set(7);
  await f.session.flush();
  assert.deepEqual(f.requests[0], f.requests[1]);
  assert.equal(f.requests[2]?.layout.x, 7);
  assert.notEqual(f.requests[1]?.mutation_id, f.requests[2]?.mutation_id);
});
test('definite validation failure retains intent and permits a corrected request', async () => {
  let fail = true;
  const f = setup(async (request) => {
    if (fail) {
      fail = false;
      throw new TerritoryRequestError('invalid', 422);
    }
    return receipt(request);
  });
  f.set(2);
  await assert.rejects(f.session.flush());
  f.set(4);
  await f.session.flush();
  assert.notEqual(f.requests[0]?.mutation_id, f.requests[1]?.mutation_id);
  assert.equal(f.requests[1]?.layout.x, 4);
});
test('authority change fences a late response and prevents publication', async () => {
  const first = deferred<SaveReceipt<Layout>>();
  const f = setup(() => first.promise);
  f.set(2);
  const saving = f.session.flush();
  f.switch();
  first.resolve(receipt(f.requests[0]!));
  await assert.rejects(saving, /Governor/);
  assert.equal(f.receipts.length, 0);
  await assert.rejects(f.session.publication(), /Governor/);
});
test('disposing aborts transport and discards a late reply', async () => {
  const first = deferred<SaveReceipt<Layout>>();
  let transportSignal: AbortSignal | undefined;
  const f = setup((_, signal) => {
    transportSignal = signal;
    return first.promise;
  });
  const saving = f.session.flush();
  f.session.dispose();
  first.resolve(receipt(f.requests[0]!));
  await assert.rejects(saving);
  assert.equal(transportSignal?.aborted, true);
  assert.equal(f.receipts.length, 0);
});
test('normalized accepted layout is installed when no later local edit exists', async () => {
  const f = setup(async (request) => ({ ...receipt(request), layout: { x: 9 } }));
  await f.session.flush();
  assert.equal(f.get().x, 9);
  assert.equal(f.session.dirty(), false);
});
test('invalid receipt cannot advance the local revision or permit publication', async () => {
  const f = setup(async (request) => ({ ...receipt(request), revision: 99 }));
  await assert.rejects(f.session.publication(), /receipt/);
  assert.equal(f.receipts.length, 0);
});
test('a conflict retains local changes and does not publish', async () => {
  const f = setup(async () => {
    throw new TerritoryRequestError('newer revision', 409);
  });
  f.set(4);
  await assert.rejects(f.session.publication(), /newer revision/);
  assert.equal(f.get().x, 4);
  assert.equal(f.session.dirty(), true);
});

test('an unrelated mutation receipt cannot acknowledge this request', async () => {
  const f = setup(async (request) => ({ ...receipt(request), mutation_id: 'unrelated' }));
  f.set(4);
  await assert.rejects(f.session.flush(), /receipt/);
  assert.equal(f.session.dirty(), true);
});
