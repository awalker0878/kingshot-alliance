export function displayUtc(value: string): string {
  return `${new Intl.DateTimeFormat('en-CA', {
    timeZone: 'UTC',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(value))} UTC`;
}

export function displayLocal(value: string): string {
  const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  return `${new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))} ${zone}`;
}

export function utcInput(value: string): string {
  return new Date(value).toISOString().slice(0, 16);
}

export function humanize(value: string | null): string {
  return value ? value.replaceAll('_', ' ') : '—';
}

export function speedups(minutes: number | null): string {
  if (minutes === null) return 'not declared';

  const hours = minutes / 60;
  return Number.isInteger(hours) ? `${hours}h` : `${hours.toFixed(1)}h`;
}
