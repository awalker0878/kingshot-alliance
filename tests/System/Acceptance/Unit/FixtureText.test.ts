import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeFixtureText } from '../Support/normalizeFixtureText.ts';

test('formatted fixture dates change without changing their surrounding labels or facts', () => {
  const first = normalizeFixtureText(
    'Observed Sep 9, 2026, 11:36 AM; power 145,000,000, TC3, 2 records',
  );
  const later = normalizeFixtureText(
    'Observed Jan 10, 2027, 1:05 PM; power 145,000,000, TC3, 2 records',
  );
  assert.deepEqual(first, later);
  assert.equal(first.text, 'Observed Fixture date; power 145,000,000, TC3, 2 records');
  assert.equal(first.dateCount, 1);
});

test('yearless event options and leap dates remain supported', () => {
  assert.deepEqual(normalizeFixtureText('Feb 29, 2024, 12:00 AM / Sep 12, 11:00 AM'), {
    text: 'Fixture date / Fixture date',
    dateCount: 2,
  });
});

for (const value of [
  'Feb 29, 2026, 1:00 AM',
  'Apr 31, 2026, 1:00 AM',
  'Sep 0, 2026, 1:00 AM',
  'Sep 10, 2026, 13:00 PM',
  'Sep 10, 2026, 0:00 AM',
  'Sep 10, 2026, 1:60 AM',
]) {
  test(`invalid date stays a failure: ${value}`, () => {
    assert.throws(() => normalizeFixtureText(value), /invalid formatted fixture date/);
  });
}

test('behavioral changes cannot be erased by normalization', () => {
  for (const [before, after] of [
    ['Current', 'Stale'],
    ['145,000,000', '120,000,000'],
    ['TC3', 'TC2'],
    ['4 blockers', '0 blockers'],
    ['2026 season', '2027 season'],
    ['1 day ago', '35 days ago'],
  ]) {
    assert.notEqual(normalizeFixtureText(before).text, normalizeFixtureText(after).text);
  }
  assert.deepEqual(normalizeFixtureText('No recorded date'), {
    text: 'No recorded date',
    dateCount: 0,
  });
});

test('only concrete fixture identity formats are normalized', () => {
  assert.equal(
    normalizeFixtureText('01ARZ3NDEKTSV4RRFFQ69G5FAV 123e4567-e89b-12d3-a456-426614174000').text,
    'fixture-id fixture-id',
  );
  assert.equal(
    normalizeFixtureText('ABCDEFGHIJKLMNOPQRSTUVWXYZ').text,
    'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
  );
});

for (const status of ['Scheduled', 'Completed', 'Cancelled']) {
  test(`adjacent occurrence ${status} preserves state across fixture dates`, () => {
    assert.deepEqual(normalizeFixtureText(`Sep 12, 11:00 AM${status}`), {
      text: `Fixture date${status}`,
      dateCount: 1,
    });
    assert.deepEqual(
      normalizeFixtureText(`Sep 13, 2026, 12:00 PM${status}`),
      normalizeFixtureText(`Sep 12, 11:00 AM${status}`),
    );
    assert.throws(
      () => normalizeFixtureText(`Feb 30, 1:00 AM${status}`),
      /invalid formatted fixture date/,
    );
  });
}

test('adjacent status spelling and behavioral changes cannot be normalized away', () => {
  assert.notEqual(
    normalizeFixtureText('Sep 13, 11:00 AMScheduled').text,
    normalizeFixtureText('Sep 13, 11:00 AMCancelled').text,
  );
  for (const suffix of ['Unknown', 'ScheduledWrong', 'PMount']) {
    const text = `Sep 13, 11:00 AM${suffix}`;
    assert.deepEqual(normalizeFixtureText(text), { text, dateCount: 0 });
  }
});
