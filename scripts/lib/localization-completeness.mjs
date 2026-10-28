export function flattenMessages(node, prefix = '', result = {}) {
  for (const [key, value] of Object.entries(node)) {
    const name = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'string') result[name] = value;
    else if (value && typeof value === 'object') flattenMessages(value, name, result);
  }
  return result;
}

export function messageParameters(value) {
  return [...value.matchAll(/\{[A-Za-z0-9_]+\}|(?<![\w/]):[A-Za-z_][A-Za-z0-9_]*/g)]
    .map((match) => match[0])
    .sort();
}

export function hasInventedNumber(source, value) {
  const prose = (text) =>
    messageParameters(text).reduce((result, parameter) => result.replaceAll(parameter, ''), text);
  const english = prose(source);
  if (
    /\bper\b/i.test(english) &&
    [...prose(value).matchAll(/\p{Decimal_Number}+/gu)].every((match) => match[0] === '1')
  )
    return false;
  // Word and Roman numeral conversions are valid. Dates or quantities added to
  // labels such as "Published at" are not.
  if (
    /\d|\b(?:zero|one|two|three|four|five|six|seven|eight|nine|ten|twelve|twenty|hundred|thousand|million|first|second|third|fourth|once|twice)\b|\bPhase\s+(?:I|II|III|IV|V)\b/i.test(
      english,
    )
  )
    return false;
  return /\p{Decimal_Number}/u.test(prose(value));
}

export function catalogueProblems(english, localized, allowedUnchanged = []) {
  const allowed = new Set(allowedUnchanged);
  const problems = [];
  for (const [key, source] of Object.entries(english)) {
    const value = localized[key];
    if (typeof value !== 'string' || !value.trim()) {
      problems.push({ key, reason: 'missing', source });
      continue;
    }
    if (JSON.stringify(messageParameters(source)) !== JSON.stringify(messageParameters(value)))
      problems.push({ key, reason: 'parameters', source, value });
    if (hasInventedNumber(source, value))
      problems.push({ key, reason: 'invented-number', source, value });
    const prose = messageParameters(source).reduce(
      (text, parameter) => text.replaceAll(parameter, ''),
      source,
    );
    if (value === source && /[A-Za-z]{2}/.test(prose) && !allowed.has(source))
      problems.push({ key, reason: 'untranslated', source });
  }
  return problems;
}

// English-only extensions must not hide an existing translation of unchanged text.
// A changed English source is deliberately excluded: its old translation may be stale.
export function preservedTranslation(source, effective, baseEnglish, baseLocalized) {
  if (effective && effective !== source) return effective;
  if (baseLocalized && baseLocalized !== source && (!baseEnglish || baseEnglish === source))
    return baseLocalized;
  return null;
}

export function expandMessages(flat) {
  const result = {};
  for (const [key, value] of Object.entries(flat)) {
    const parts = key.split('.');
    let node = result;
    for (const part of parts.slice(0, -1)) {
      node[part] ??= {};
      node = node[part];
    }
    node[parts.at(-1)] = value;
  }
  return result;
}
