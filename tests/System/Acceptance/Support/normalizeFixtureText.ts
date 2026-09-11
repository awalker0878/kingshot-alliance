/** Normalize fixture identities and validated, formatted dates, not behavioral text. */
export function normalizeFixtureText(input: string): { text: string; dateCount: number } {
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  let dateCount = 0;
  // Adjacent occurrence spans have no whitespace in innerText. Recognize only
  // the current English status labels; do not consume or normalize the status.
  const text = input
    .replace(
      /\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d{1,2}), (?:(\d{4}), )?(\d{1,2}):(\d{2}) (AM|PM)(?=\b|(?:Scheduled|Completed|Cancelled)\b)/g,
      (
        _match,
        month: string,
        day: string,
        year: string | undefined,
        hour: string,
        minute: string,
      ) => {
        const calendarYear = Number(year ?? 2000);
        const monthIndex = months.indexOf(month);
        const maximumDay = new Date(Date.UTC(calendarYear, monthIndex + 1, 0)).getUTCDate();
        if (
          Number(day) < 1 ||
          Number(day) > maximumDay ||
          Number(hour) < 1 ||
          Number(hour) > 12 ||
          Number(minute) > 59
        ) {
          throw new Error('An acceptance surface contains an invalid formatted fixture date.');
        }
        dateCount++;
        return 'Fixture date';
      },
    )
    .replace(
      /\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/gi,
      'fixture-id',
    )
    .replace(/\b[0-7][0-9a-hjkmnp-tv-z]{25}\b/gi, 'fixture-id')
    .replace(/\s+/g, ' ')
    .trim();
  return { text, dateCount };
}
