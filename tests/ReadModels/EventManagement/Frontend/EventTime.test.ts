import assert from 'node:assert/strict';
import test from 'node:test';
import {
  calendarDateInZone,
  calendarMonthDays,
  eventDateKey,
  formatEventTime,
  resolveTimeZone,
  shiftCalendarMonth,
} from '../../../../resources/js/features/events/time.ts';

const clock: Intl.DateTimeFormatOptions = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };

test('event clock and calendar day use the same explicit account timezone', () => {
  const instant = '2026-10-26T00:30:00Z';
  for (const [zone, time, date] of [
    ['America/Toronto', '20:30', '2026-10-25'],
    ['UTC', '00:30', '2026-10-26'],
    ['America/Sao_Paulo', '21:30', '2026-10-25'],
    ['Asia/Baghdad', '03:30', '2026-10-26'],
  ]) {
    assert.equal(formatEventTime(instant, 'en', zone!, clock), time);
    assert.equal(eventDateKey(instant, zone!), date);
    assert.equal(calendarDateInZone(instant, zone!).toISOString(), `${date}T12:00:00.000Z`);
  }
});

test('spring-forward and repeated fall-back instants retain their real offsets', () => {
  const zone = 'America/Toronto';
  assert.equal(formatEventTime('2026-03-08T06:30:00Z', 'en', zone, clock), '01:30');
  assert.equal(formatEventTime('2026-03-08T07:30:00Z', 'en', zone, clock), '03:30');
  const withOffset = { ...clock, timeZoneName: 'shortOffset' } as const;
  assert.equal(formatEventTime('2026-11-01T05:30:00Z', 'en', zone, withOffset), '01:30 GMT-4');
  assert.equal(formatEventTime('2026-11-01T06:30:00Z', 'en', zone, withOffset), '01:30 GMT-5');
});

test('calendar traversal handles DST months, leap days and year changes as civil dates', () => {
  for (const [month, count] of [
    ['2026-03', 31],
    ['2026-11', 30],
    ['2024-02', 29],
  ] as const) {
    const days = calendarMonthDays(new Date(`${month}-15T12:00:00Z`));
    assert.equal(days.filter((day) => day.current).length, count);
    assert.equal(new Set(days.map((day) => day.key)).size, days.length);
    assert.equal(days[0]!.date.getUTCDay(), 0);
    assert.equal(days.at(-1)!.date.getUTCDay(), 6);
  }
  assert.equal(
    shiftCalendarMonth(new Date('2026-12-31T12:00:00Z'), 1).toISOString(),
    '2027-01-01T12:00:00.000Z',
  );
});

test('locale changes preserve the event day and invalid account zones explicitly use UTC', () => {
  assert.equal(resolveTimeZone('invalid/timezone'), 'UTC');
  assert.equal(resolveTimeZone(null), 'UTC');
  const instant = '2026-10-25T18:00:00Z';
  for (const locale of ['en', 'pt-BR', 'fr', 'ar']) {
    assert.equal(
      formatEventTime(instant, locale, 'UTC', clock),
      new Intl.DateTimeFormat(locale, { ...clock, timeZone: 'UTC' }).format(new Date(instant)),
    );
  }
});
