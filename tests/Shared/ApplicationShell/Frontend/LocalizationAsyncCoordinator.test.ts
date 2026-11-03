import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';
import { createLocaleLoadCoordinator } from '../../../../resources/js/localization/async-coordinator.ts';

type Locale = 'en' | 'ar' | 'fr';
type Domain = 'core' | 'A' | 'B';

function deferred() {
  let resolve!: () => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<void>((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}

function harness() {
  const requests: Array<{
    locale: Locale;
    domains: Domain[];
    gate: ReturnType<typeof deferred>;
  }> = [];
  const commits: Array<{ locale: Locale; persist: boolean }> = [];
  const failures: unknown[] = [];
  const persisted: Locale[] = [];
  const appliedLocales: Locale[] = [];
  const storage = new Map<string, Locale>();
  const coordinator = createLocaleLoadCoordinator<Locale, Domain>({
    initialLocale: 'en',
    coreDomain: 'core',
    domainsForPage: (name) => [name as Domain],
    load(locale, domains) {
      const gate = deferred();
      requests.push({ locale, domains, gate });
      return gate.promise;
    },
    commit(locale) {
      appliedLocales.push(locale);
    },
    persistSelection(locale, persist) {
      commits.push({ locale, persist });
      if (persist) {
        persisted.push(locale);
        storage.set('kingshot.locale', locale);
      }
    },
    loadFailed(error) {
      failures.push(error);
    },
  });
  return { coordinator, requests, commits, failures, persisted, appliedLocales, storage };
}

test('rapid Arabic then French switching only commits French even if Arabic finishes last', async () => {
  const h = harness();
  const arabic = h.coordinator.setLocale('ar');
  const french = h.coordinator.setLocale('fr');
  assert.deepEqual(h.requests.map((request) => request.locale), ['ar', 'fr']);
  h.requests[1]!.gate.resolve();
  await french;
  h.requests[0]!.gate.resolve();
  await arabic;
  assert.equal(h.coordinator.getLocale(), 'fr');
  assert.deepEqual(h.commits, [{ locale: 'fr', persist: true }]);
  assert.deepEqual(h.appliedLocales, ['fr']);
  assert.deepEqual(h.persisted, ['fr'], 'stale Arabic load must never overwrite stored preference');
  assert.equal(h.storage.get('kingshot.locale'), 'fr', 'the persisted locale must remain French');
});

test('stale page A load cannot overwrite page B after navigation', async () => {
  const h = harness();
  const pageA = h.coordinator.ensurePageDomains('A');
  const pageB = h.coordinator.ensurePageDomains('B');
  assert.deepEqual(h.requests.map((request) => request.domains), [
    ['core', 'A'],
    ['core', 'B'],
  ]);
  h.requests[1]!.gate.resolve();
  await pageB;
  h.requests[0]!.gate.resolve();
  await pageA;
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'B']);
});

test('locale selection reloads the newest navigated page domains before committing', async () => {
  const h = harness();
  const pageA = h.coordinator.ensurePageDomains('A');
  const arabic = h.coordinator.setLocale('ar');
  const pageB = h.coordinator.ensurePageDomains('B');

  assert.deepEqual(h.requests.map(({ locale, domains }) => [locale, ...domains]), [
    ['en', 'core', 'A'],
    ['ar', 'core', 'A'],
    ['en', 'core', 'B'],
  ]);
  h.requests[2]!.gate.resolve();
  await pageB;
  h.requests[1]!.gate.resolve();
  await Promise.resolve();
  assert.deepEqual(h.requests[3] && [h.requests[3].locale, ...h.requests[3].domains], [
    'ar', 'core', 'B',
  ]);
  assert.deepEqual(h.commits, []);
  h.requests[3]!.gate.resolve();
  await arabic;
  h.requests[0]!.gate.resolve();
  await pageA;
  assert.equal(h.coordinator.getLocale(), 'ar');
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'B']);
  assert.deepEqual(h.commits, [{ locale: 'ar', persist: true }]);
  assert.deepEqual(h.persisted, ['ar']);
  assert.equal(h.storage.get('kingshot.locale'), 'ar');
});

test('a rejected locale import commits nothing, signals failure, and permits a later retry', async () => {
  const h = harness();
  const failed = h.coordinator.setLocale('ar');
  const networkError = new Error('simulated chunk import failure');
  h.requests[0]!.gate.reject(networkError);
  await assert.rejects(failed, /simulated chunk import failure/);
  assert.equal(h.coordinator.getLocale(), 'en');
  assert.deepEqual(h.commits, []);
  assert.deepEqual(h.appliedLocales, []);
  assert.deepEqual(h.persisted, [], 'failed loading cannot persist a selection');
  assert.equal(h.storage.has('kingshot.locale'), false);
  assert.deepEqual(h.failures, [networkError]);

  const retried = h.coordinator.setLocale('ar');
  h.requests[1]!.gate.resolve();
  await retried;
  assert.equal(h.coordinator.getLocale(), 'ar');
  assert.deepEqual(h.commits, [{ locale: 'ar', persist: true }]);
});

// Entrypoint DOM mutation tests use their own isolated fixture and aliases.
{
type Locale = 'en' | 'ar' | 'fr';
type Domain = 'core';

function deferred() {
  let resolve!: () => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<void>((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}

function evaluateModule(
  file: string,
  requireMock: (specifier: string) => unknown,
  globals: Record<string, unknown> = {},
  sourceOverride?: string,
) {
  const source = sourceOverride ?? readFileSync(file, 'utf8');
  const javascript = ts.transpileModule(source, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
  }).outputText;
  const exports: Record<string, unknown> = {};
  runInNewContext(javascript, { exports, require: requireMock, ...globals });
  return exports;
}

function actualFrontendHarness(coordinatorSourceOverride?: string) {
  const coordinatorModule = evaluateModule(
    'resources/js/localization/async-coordinator.ts',
    (specifier) => {
      throw new Error('Unexpected coordinator import: ' + specifier);
    },
    {},
    coordinatorSourceOverride,
  );
  const requests: Array<{ locale: Locale; domains: Domain[]; gate: ReturnType<typeof deferred> }> = [];
  const writes = new Map<string, string>();
  const cookieWrites: string[] = [];
  const documentElement = { lang: 'en', dir: 'ltr' };
  const browserWindow = {
    location: { protocol: 'https:' },
    localStorage: {
      setItem(key: string, value: string) {
        writes.set(key, value);
      },
    },
  };
  const fakeDocument = {
    documentElement,
    set cookie(value: string) {
      cookieWrites.push(value);
    },
  };
  const requireMock = (specifier: string): unknown => {
    if (specifier === 'vue') {
      return {
        ref: (value: unknown) => ({ value }),
        readonly: (value: unknown) => value,
        computed: (fn: () => unknown) => ({ get value() { return fn(); } }),
      };
    }
    if (specifier === './async-coordinator') return coordinatorModule;
    if (specifier === './loader') {
      return {
        loadDomains(locale: Locale, domains: Domain[]) {
          const gate = deferred();
          requests.push({ locale, domains, gate });
          return gate.promise;
        },
        resolveMessage: () => null,
      };
    }
    if (specifier === './locales') {
      return {
        defaultLocale: 'en',
        localeDefinition: (code: Locale) => ({ code, direction: code === 'ar' ? 'rtl' : 'ltr' }),
        locales: [{ code: 'en' }, { code: 'ar' }, { code: 'fr' }],
        normalizeLocale: (locale: unknown) => locale,
      };
    }
    if (specifier === './registry') {
      return { domainsForPage: () => [], hasLocaleCatalogue: () => true };
    }
    throw new Error('Unexpected localization entrypoint import: ' + specifier);
  };
  const index = evaluateModule('resources/js/localization/index.ts', requireMock, {
    window: browserWindow,
    document: fakeDocument,
  }) as {
    setLocale(locale: Locale, persist?: boolean): Promise<void>;
    useLocale(): { locale: { value: Locale } };
  };

  return { index, requests, writes, cookieWrites, documentElement };
}

test('real index: stale Arabic completion never flips document lang/dir or persisted French', async () => {
  const h = actualFrontendHarness();
  const arabic = h.index.setLocale('ar');
  const french = h.index.setLocale('fr');
  assert.deepEqual(h.requests.map(({ locale }) => locale), ['ar', 'fr']);
  assert.equal(h.documentElement.lang, 'en');
  assert.equal(h.documentElement.dir, 'ltr');
  h.requests[1]!.gate.resolve();
  await french;
  assert.equal(h.index.useLocale().locale.value, 'fr');
  assert.equal(h.documentElement.lang, 'fr');
  assert.equal(h.documentElement.dir, 'ltr');
  assert.equal(h.writes.get('kingshot.locale'), 'fr');
  assert.equal(h.cookieWrites.length, 1);
  assert.match(h.cookieWrites[0] ?? '', /^kingshot_locale=fr;/);

  h.requests[0]!.gate.resolve();
  await arabic;
  assert.equal(h.index.useLocale().locale.value, 'fr');
  assert.equal(h.documentElement.lang, 'fr', 'superseded Arabic must not alter document language');
  assert.equal(h.documentElement.dir, 'ltr', 'superseded Arabic must not flip the document to RTL');
  assert.equal(h.writes.get('kingshot.locale'), 'fr');
  assert.equal(h.cookieWrites.length, 1);
  console.log('STALE DOM COMMIT REJECTED: Arabic finished last; document fr/ltr and persisted fr retained');
});

test('real index: failed Arabic load cannot touch DOM/cookie/storage, retry commits ar/rtl', async () => {
  const h = actualFrontendHarness();
  const failed = h.index.setLocale('ar');
  h.requests[0]!.gate.reject(new Error('first import unavailable'));
  await assert.rejects(failed, /first import unavailable/);
  assert.equal(h.index.useLocale().locale.value, 'en');
  assert.equal(h.documentElement.lang, 'en');
  assert.equal(h.documentElement.dir, 'ltr');
  assert.equal(h.cookieWrites.length, 0);
  assert.equal(h.writes.has('kingshot.locale'), false);

  const retry = h.index.setLocale('ar');
  h.requests[1]!.gate.resolve();
  await retry;
  assert.equal(h.index.useLocale().locale.value, 'ar');
  assert.equal(h.documentElement.lang, 'ar');
  assert.equal(h.documentElement.dir, 'rtl');
  assert.equal(h.writes.get('kingshot.locale'), 'ar');
  assert.equal(h.cookieWrites.length, 1);
  assert.match(h.cookieWrites[0] ?? '', /^kingshot_locale=ar;/);
  console.log('FAILED LOAD PRESERVES DOM: en/ltr retained until successful retry commits ar/rtl');
});

test('DOM guard negative control: removing the generation checks permits stale Arabic RTL', async () => {
  const source = readFileSync('resources/js/localization/async-coordinator.ts', 'utf8');
  const guard = 'if (request !== localeRequest) return;';
  assert.equal(source.split(guard).length - 1, 2, 'the source must have both stale-generation checks');
  const broken = source.replaceAll(guard, '/* mutation: stale locale request may commit */');
  const h = actualFrontendHarness(broken);

  const arabic = h.index.setLocale('ar');
  const french = h.index.setLocale('fr');
  h.requests[1]!.gate.resolve();
  await french;
  assert.equal(h.documentElement.dir, 'ltr');
  assert.equal(h.documentElement.lang, 'fr');
  h.requests[0]!.gate.resolve();
  await arabic;

  assert.equal(h.index.useLocale().locale.value, 'ar', 'the mutant wrongly commits stale Arabic');
  assert.equal(h.documentElement.lang, 'ar', 'mutant must reproduce stale document language');
  assert.equal(h.documentElement.dir, 'rtl', 'mutant must reproduce visible RTL flip');
  assert.equal(h.writes.get('kingshot.locale'), 'ar', 'mutant must reproduce stale persistence');
  assert.throws(
    () => assert.equal(h.documentElement.dir, 'ltr', 'superseded Arabic must not flip to RTL'),
    /superseded Arabic must not flip to RTL/,
    'the positive DOM direction assertion must fail on the deliberately broken coordinator',
  );
  console.log('STALE DOM MUTATION DETECTED: removing generation guards caused ar/rtl and stale persistence');
});
}
