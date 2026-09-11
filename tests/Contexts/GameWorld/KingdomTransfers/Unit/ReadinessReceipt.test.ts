import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeReadinessReceipt } from '../Support/normalizeReadinessReceipt.ts';

const id = '01M29846NC1WYGXV7K6EP1TEK8';

test('receipt normalization preserves surrounding labels, status and spacing', () => {
  for (const prefix of [' · Destination receipt ', 'Succeeded · Destination receipt ']) {
    assert.equal(normalizeReadinessReceipt(`${prefix}${id} `), `${prefix}fixture `);
  }
  assert.notEqual(
    normalizeReadinessReceipt(`Failed · Destination receipt ${id}`),
    normalizeReadinessReceipt(`Succeeded · Destination receipt ${id}`),
  );
});

test('changing a concrete receipt identity does not change other rendered facts', () => {
  assert.equal(
    normalizeReadinessReceipt(` · Destination receipt ${id}`),
    normalizeReadinessReceipt(' · Destination receipt 01ARZ3NDEKTSV4RRFFQ69G5FAV'),
  );
});

for (const text of ['', 'Destination receipt missing', `Destination receipt ${id} ${id}`]) {
  test(`invalid receipt shape fails: ${text || 'empty'}`, () => {
    assert.throws(() => normalizeReadinessReceipt(text), /exactly one ULID/);
  });
}
