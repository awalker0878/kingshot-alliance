/** Normalize the one generated receipt ID; preserve the actual label, state and spacing. */
export function normalizeReadinessReceipt(text: string): string {
  const matches = [...text.matchAll(/\b[0-7][0-9A-HJKMNP-TV-Z]{25}\b/g)];
  if (matches.length !== 1) {
    throw new Error('A rendered destination receipt must contain exactly one ULID.');
  }
  const match = matches[0]!;
  return text.slice(0, match.index) + 'fixture' + text.slice(match.index + match[0].length);
}
