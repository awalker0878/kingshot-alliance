// UI key corrections are context-specific. A broad source-text correction must
// not replace an existing contextual translation. All selected values still
// pass the compiler's catalogue/placeholder validation before any file is written.
export function selectUiTranslation({ keyCorrection, preserved, reviewed, draft }) {
  return keyCorrection ?? preserved ?? selectServerTranslation({ reviewed, draft });
}

export function selectServerTranslation({ reviewed, draft }) {
  return reviewed ?? draft;
}
