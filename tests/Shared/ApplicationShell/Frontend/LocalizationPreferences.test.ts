import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { createLocaleLoadCoordinator } from '../../../../resources/js/localization/async-coordinator.ts';
import { createLocalePreferences, parsePreference, serializePreference, preferenceCookie, preferenceStorage, legacyCookie, legacyStorage, type LocalePreference } from '../../../../resources/js/localization/preferences.ts';
import type { LocaleCode } from '../../../../resources/js/localization/locales.ts';

const record = (locale: LocaleCode, revision = 0, id = 'a', epoch = 'a'): LocalePreference => ({ version: 1, epoch: epoch.repeat(32), revision, selectionId: id.repeat(32), locale });
function sharedState(initial: LocalePreference | null = record('en')) {
  return { cookies: new Map(initial ? [[preferenceCookie, serializePreference(initial)]] : []), storage: new Map<string, string>(), writes: [] as string[] };
}
function tab(shared: ReturnType<typeof sharedState>, id = 'b') {
  const state = { locale: 'en' as LocaleCode, desired: 'en' as LocaleCode, blockedCookie: false, blockedStorage: false, blockedRead: false, fail: false, delayed: false, loads: 0 };
  const gates: Array<() => void> = [];
  const coordinator = createLocaleLoadCoordinator<LocaleCode, 'core'>({
    initialLocale: 'en', coreDomain: 'core', domainsForPage: () => [],
    async load() {
      state.loads++;
      if (state.delayed) await new Promise<void>((resolve) => gates.push(resolve));
      if (state.fail) throw new Error('chunk unavailable');
    },
    commit(locale) { state.locale = locale; },
  });
  let counter = 0;
  const preferences = createLocalePreferences({
    readCookie(name) { if (state.blockedRead) throw new Error('cookie read blocked'); return shared.cookies.get(name) ?? null; },
    writeCookie(name, value) { if (!state.blockedCookie) shared.cookies.set(name, value); },
    writeStorage(key, value) { if (state.blockedStorage) throw new Error('storage blocked'); shared.storage.set(key, value); shared.writes.push(key); },
    fallbackLocale: () => 'en', newId: () => id.repeat(28) + (++counter).toString(16).padStart(4, '0'),
    load: (locale, authorize) => coordinator.setLocale(locale, false, authorize),
    desired(locale) { state.desired = locale; },
  });
  return { ...preferences, state, gates };
}

test('cookie wire contract shares valid and rejected cases with PHP', () => {
  const cases = JSON.parse(readFileSync('resources/data/localization/locale-preference-cases.json', 'utf8'));
  for (const item of cases) assert.equal(parsePreference(item.value)?.locale ?? null, item.expected, JSON.stringify(item.value));
});

test('startup valid cookie overrides both stale storage formats; legacy imports once', async () => {
  const shared = sharedState(record('fr'));
  shared.cookies.set(legacyCookie, 'ar');
  shared.storage.set(legacyStorage, 'ar');
  shared.storage.set(preferenceStorage, serializePreference(record('ar', 99)));
  const a = tab(shared);
  await a.initialize();
  assert.equal(a.state.locale, 'fr');
  assert.equal(shared.storage.get(legacyStorage), 'fr');
  const legacy = sharedState(null);
  legacy.cookies.set(legacyCookie, 'ar');
  legacy.storage.set(legacyStorage, 'fr');
  const b = tab(legacy);
  await b.initialize();
  assert.equal(b.state.locale, 'ar');
  assert.equal(parsePreference(legacy.cookies.get(preferenceCookie))?.locale, 'ar');
});

test('failed local selection writes nothing; retry reuses its selection identity', async () => {
  const shared = sharedState(); const a = tab(shared);
  await a.initialize();
  const before = shared.cookies.get(preferenceCookie);
  a.state.fail = true;
  await assert.rejects(a.select('fr'), /chunk unavailable/);
  assert.equal(shared.cookies.get(preferenceCookie), before);
  assert.equal(a.state.locale, 'en');
  a.state.fail = false;
  await a.retry();
  assert.equal(a.state.locale, 'fr');
  assert.equal(parsePreference(shared.cookies.get(preferenceCookie))?.revision, 1);
});

test('concurrent equal-revision selections converge by identifier in opposite event orders', async () => {
  for (const reverse of [false, true]) {
    const shared = sharedState(); const a = tab(shared, 'b'); const b = tab(shared, 'c');
    await a.initialize(); await b.initialize();
    a.state.delayed = b.state.delayed = true;
    const first = a.select('fr'); const second = b.select('ar');
    a.gates.shift()!(); await first;
    const older = shared.cookies.get(preferenceCookie)!;
    b.gates.shift()!(); await second;
    const newer = shared.cookies.get(preferenceCookie)!;
    a.state.delayed = b.state.delayed = false;
    for (const event of reverse ? [newer, older] : [older, newer]) {
      await a.synchronize(event); await b.synchronize(event);
    }
    assert.equal(a.state.locale, 'ar'); assert.equal(b.state.locale, 'ar');
    assert.equal(shared.cookies.get(preferenceCookie), newer);
    const count = shared.writes.length;
    await a.synchronize(older); await a.synchronize(newer); await b.synchronize(newer);
    assert.equal(shared.writes.length, count, 'duplicate notifications must not echo');
  }
});

test('ABA cookie value cannot authorize a delayed selection from before the newer metadata', async () => {
  const shared = sharedState(); const a = tab(shared, 'b'); const b = tab(shared, 'c');
  await a.initialize(); await b.initialize();
  a.state.delayed = true;
  const pending = a.select('fr');
  await b.select('ar'); await b.select('en');
  const winner = shared.cookies.get(preferenceCookie);
  a.state.delayed = false; a.gates.shift()!(); await pending;
  assert.equal(a.state.locale, 'en'); assert.equal(shared.cookies.get(preferenceCookie), winner);
  assert.equal(parsePreference(winner)?.revision, 2);
});

test('a live tab repairs an interleaved older cookie and ignores a replay from another epoch', async () => {
  const shared = sharedState(); const a = tab(shared);
  await a.initialize(); await a.select('fr');
  const winner = shared.cookies.get(preferenceCookie)!;
  shared.cookies.set(preferenceCookie, serializePreference(record('en')));
  await a.synchronize(serializePreference(record('ar', 999, 'e', 'e')));
  assert.equal(shared.cookies.get(preferenceCookie), winner);
  assert.equal(a.state.locale, 'fr');
  const writes = shared.writes.length;
  await a.synchronize(winner);
  assert.equal(shared.writes.length, writes);
});

test('epoch reset beats old high revisions and pending imports; corrupt metadata ignores stale storage', async () => {
  const shared = sharedState(record('en', 100)); const a = tab(shared);
  await a.initialize(); a.state.delayed = true;
  const old = a.select('fr');
  shared.cookies.set(preferenceCookie, serializePreference(record('ar', 0, 'd', 'd')));
  a.state.delayed = false; a.gates.shift()!(); await old;
  assert.equal(a.state.locale, 'ar');
  await a.synchronize(serializePreference(record('fr', 999)));
  assert.equal(a.state.locale, 'ar');
  shared.cookies.set(preferenceCookie, 'broken'); shared.cookies.set(legacyCookie, 'en');
  await a.synchronize(serializePreference(record('ar', 999)));
  assert.equal(a.state.locale, 'en');
  assert.notEqual(parsePreference(shared.cookies.get(preferenceCookie))?.epoch, 'd'.repeat(32));
});

test('remote intent survives failed UI import and retry follows the newest desired locale', async () => {
  const shared = sharedState(); const a = tab(shared); const b = tab(shared, 'c');
  await a.initialize(); await b.initialize();
  a.state.fail = true; await b.select('fr');
  await assert.rejects(a.synchronize(shared.storage.get(preferenceStorage)), /chunk unavailable/);
  assert.equal(a.state.locale, 'en'); assert.equal(a.state.desired, 'fr');
  await b.select('ar');
  a.state.fail = false; await a.retry();
  assert.equal(a.state.locale, 'ar'); assert.equal(a.state.desired, 'ar');
});

test('blocked cookie writes keep changes local; blocked storage still converges on focus', async () => {
  const shared = sharedState(); const a = tab(shared); const b = tab(shared, 'c');
  await a.initialize(); await b.initialize();
  a.state.blockedCookie = true;
  const stored = shared.storage.get(preferenceStorage);
  await a.select('fr'); await a.synchronize();
  assert.equal(a.state.locale, 'fr'); assert.equal(shared.storage.get(preferenceStorage), stored);
  b.state.blockedStorage = true; await b.select('ar');
  await a.synchronize();
  assert.equal(a.state.locale, 'ar');
  a.state.blockedRead = true; await a.select('fr'); await a.synchronize();
  assert.equal(a.state.locale, 'fr');
});

test('exhausted revision resets only after successful load; next selection increments new epoch', async () => {
  const shared = sharedState(record('en', Number.MAX_SAFE_INTEGER)); const a = tab(shared);
  await a.initialize(); const old = shared.cookies.get(preferenceCookie);
  a.state.fail = true; await assert.rejects(a.select('fr'));
  assert.equal(shared.cookies.get(preferenceCookie), old);
  a.state.fail = false; await a.retry();
  const reset = parsePreference(shared.cookies.get(preferenceCookie))!;
  assert.equal(reset.revision, 1); assert.notEqual(reset.epoch, 'a'.repeat(32));
  await a.select('ar');
  assert.equal(parsePreference(shared.cookies.get(preferenceCookie))?.revision, 2);
});
