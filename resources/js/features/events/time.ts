/** Event instants use the account timezone; calendar cells are civil dates in UTC. */
export function resolveTimeZone(value: string | null | undefined): string {
  try {
    return new Intl.DateTimeFormat('en', { timeZone: value || 'UTC' }).resolvedOptions().timeZone;
  } catch {
    return 'UTC';
  }
}

export function formatEventTime(
  value: Date | string | number,
  locale: string,
  timeZone: string,
  options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },
): string {
  return new Intl.DateTimeFormat(locale, {
    ...options,
    timeZone: resolveTimeZone(timeZone),
  }).format(new Date(value));
}

export function eventDateKey(value: Date | string | number, timeZone: string): string {
  const parts = new Intl.DateTimeFormat('en-CA-u-ca-gregory-nu-latn', {
    timeZone: resolveTimeZone(timeZone),
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date(value));
  const part = (type: string) => parts.find((item) => item.type === type)?.value;
  return `${part('year')}-${part('month')}-${part('day')}`;
}

export function calendarDateInZone(value: Date | string | number, timeZone: string): Date {
  return new Date(`${eventDateKey(value, timeZone)}T12:00:00Z`);
}

export function shiftCalendarMonth(cursor: Date, delta: number): Date {
  return new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + delta, 1, 12));
}

export function calendarMonthDays(
  cursor: Date,
): Array<{ date: Date; key: string; current: boolean }> {
  const month = cursor.getUTCMonth();
  const start = shiftCalendarMonth(cursor, 0);
  const end = shiftCalendarMonth(cursor, 1);
  end.setUTCDate(0);
  start.setUTCDate(1 - start.getUTCDay());
  end.setUTCDate(end.getUTCDate() + 6 - end.getUTCDay());
  const days = [];
  for (const date = new Date(start); date <= end; date.setUTCDate(date.getUTCDate() + 1)) {
    days.push({
      date: new Date(date),
      key: eventDateKey(date, 'UTC'),
      current: date.getUTCMonth() === month,
    });
  }
  return days;
}
