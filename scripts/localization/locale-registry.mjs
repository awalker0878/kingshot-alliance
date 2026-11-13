import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

export function validateLocaleRegistry(registry) {
  assert.equal(registry.version, 1, 'Unsupported locale registry version');
  assert.ok(Array.isArray(registry.locales) && registry.locales.length, 'Missing locales');
  const codes = new Set();
  const aliases = [];
  for (const locale of registry.locales) {
    assert.match(locale.code, /^[a-z]{2,3}(?:-[A-Z]{2})?$/, 'Noncanonical locale code');
    assert.ok(!codes.has(locale.code), `Duplicate locale ${locale.code}`);
    codes.add(locale.code);
    for (const name of ['nativeName', 'englishName'])
      assert.ok(typeof locale[name] === 'string' && locale[name].trim(), `Missing ${name}`);
    assert.ok(['ltr', 'rtl'].includes(locale.direction), 'Invalid text direction');
    assert.ok(Array.isArray(locale.aliases) && locale.aliases.length, 'Missing aliases');
    for (const alias of locale.aliases) {
      assert.match(alias.tag, /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/, 'Noncanonical alias');
      assert.equal(typeof alias.descendants, 'boolean', 'Declare alias descendant policy');
      aliases.push({ ...alias, code: locale.code });
    }
    assert.ok(
      locale.aliases.some(({ tag }) => tag === locale.code.toLowerCase()),
      `Missing canonical alias for ${locale.code}`,
    );
  }
  assert.ok(codes.has(registry.defaultLocale), 'Default must be a supported locale');
  for (let i = 0; i < aliases.length; i++) {
    for (const other of aliases.slice(i + 1)) {
      const alias = aliases[i];
      assert.ok(
        alias.tag !== other.tag &&
          !(alias.descendants && other.tag.startsWith(alias.tag + '-')) &&
          !(other.descendants && alias.tag.startsWith(other.tag + '-')),
        `Ambiguous locale aliases: ${alias.tag}, ${other.tag}`,
      );
    }
  }
  return registry;
}

export function readLocaleRegistry() {
  return validateLocaleRegistry(
    JSON.parse(
      readFileSync(
        new URL('../../resources/data/localization/locales.json', import.meta.url),
        'utf8',
      ),
    ),
  );
}

export function translatedLocaleCodes(registry = readLocaleRegistry()) {
  return registry.locales.map(({ code }) => code).filter((code) => code !== registry.defaultLocale);
}
