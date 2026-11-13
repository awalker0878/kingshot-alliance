import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

type Locale = 'en' | 'ar' | 'fr';
type Domain = 'core' | 'A' | 'B';

function deferred() {
  let resolve!: () => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<void>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

function originalImplementation() {
  const original = execFileSync('git', [
    'show',
    '7cf387b628bbbaeb81d10120340e559b46403bee:resources/js/localization/index.ts',
  ], { encoding: 'utf8' });
  assert.match(original, /currentPageDomains = domains;/);
  const compiled = ts.transpileModule(original, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
  }).outputText;
  const requests: Array<{ locale: Locale; domains: Domain[]; gate: ReturnType<typeof deferred> }> = [];
  const writes: Locale[] = [];
  const persisted = new Map<string, Locale>();
  const cookies: string[] = [];
  let activeDocumentLocale: string = 'en';
  let resolvedDomains: Domain[] = [];
  const exports: Record<string, unknown> = {};

  const requireMock = (specifier: string): unknown => {
    if (specifier === 'vue') return {
      ref: (value: unknown) => ({ value }),
      readonly: (value: unknown) => value,
      computed: (fn: () => unknown) => ({ get value() { return fn(); } }),
    };
    if (specifier === './loader') return {
      loadDomains: (locale: Locale, domains: Domain[]) => {
        const gate = deferred();
        requests.push({ locale, domains, gate });
        return gate.promise;
      },
      resolveMessage: (_locale: Locale, domains: Domain[]) => {
        resolvedDomains = [...domains];
        return 'found';
      },
    };
    if (specifier === './locales') return {
      defaultLocale: 'en',
      localeDefinition: (code: string) => ({ code, direction: code === 'ar' ? 'rtl' : 'ltr' }),
      locales: [{ code: 'en' }, { code: 'ar' }, { code: 'fr' }],
      normalizeLocale: (value: unknown) => value,
    };
    if (specifier === './registry') return {
      domainsForPage: (name: string) => [name],
      hasLocaleCatalogue: () => true,
    };
    throw new Error('Unexpected original module dependency: ' + specifier);
  };
  runInNewContext(compiled, {
    exports,
    require: requireMock,
    window: {
      localStorage: {
        setItem: (key: string, value: Locale) => { writes.push(value); persisted.set(key, value); },
      },
      location: { protocol: 'https:' },
    },
    document: {
      documentElement: {
        get lang() { return activeDocumentLocale; },
        set lang(value: string) { activeDocumentLocale = value; },
        dir: 'ltr',
      },
      set cookie(value: string) { cookies.push(value); },
    },
  });
  return { module: exports as {
    ensurePageDomains(name: string): Promise<void>;
    setLocale(locale: Locale): Promise<void>;
    t(key: string): string;
  }, requests, writes, persisted, cookies, activeLocale: () => activeDocumentLocale,
    observedDomains: () => resolvedDomains };
}

test('actual pre-fix source exhibits stale page-domain completion (negative control)', async () => {
  const old = originalImplementation();
  const pageA = old.module.ensurePageDomains('A');
  const pageB = old.module.ensurePageDomains('B');
  old.requests[1]!.gate.resolve();
  await pageB;
  old.requests[0]!.gate.resolve();
  await pageA;
  old.module.t('probe');
  assert.deepEqual(old.observedDomains(), ['core', 'A'], 'the original incorrectly restores page A');
  console.log('LEGACY RACE REPRODUCED: stale page A overwrote active page B');
});

test('actual pre-fix source already guards Arabic→French preference commit (baseline characterization)', async () => {
  const old = originalImplementation();
  const arabic = old.module.setLocale('ar');
  const french = old.module.setLocale('fr');
  old.requests[1]!.gate.resolve();
  await french;
  old.requests[0]!.gate.resolve();
  await arabic;
  assert.deepEqual(old.writes, ['fr'], 'existing locale request-generation guard already protected persistence');
  assert.equal(old.persisted.get('kingshot.locale'), 'fr', 'original persisted value remains French');
  assert.equal(old.activeLocale(), 'fr', 'original document language remains French');
  assert.equal(old.cookies.length, 1, 'only the winning locale writes the cookie');
  assert.match(old.cookies[0] ?? '', /^kingshot_locale=fr;/);
  console.log('LEGACY BEHAVIOR CONFIRMED: original Arabic→French guard preserves current, cookie and stored locale=fr');
});

test('actual pre-fix source rejects a failed locale load without persisting a preference', async () => {
  const old = originalImplementation();
  const request = old.module.setLocale('ar');
  old.requests[0]!.gate.reject(new Error('simulated loader failure'));
  await assert.rejects(request, /simulated loader failure/);
  assert.equal(old.activeLocale(), 'en', 'original stays in English after failure');
  assert.equal(old.persisted.has('kingshot.locale'), false, 'original does not persist failed choice');
  assert.deepEqual(old.writes, []);
  assert.deepEqual(old.cookies, []);
  console.log('LEGACY BEHAVIOR CONFIRMED: failed original locale load leaves persisted preference unchanged');
});

test('actual pre-fix source misses new page domains when navigation overtakes a language switch', async () => {
  const old = originalImplementation();

  const initialPage = old.module.ensurePageDomains('A');
  old.requests[0]!.gate.resolve();
  await initialPage;

  const arabic = old.module.setLocale('ar');
  assert.deepEqual([...old.requests[1]!.domains], ['core', 'A']);

  const pageB = old.module.ensurePageDomains('B');
  old.requests[2]!.gate.resolve();
  await pageB;

  old.requests[1]!.gate.resolve();
  await arabic;
  old.module.t('probe');

  assert.equal(old.activeLocale(), 'ar');
  assert.deepEqual(old.observedDomains(), ['core', 'B']);
  assert.equal(
    old.requests.some((request) => request.locale === 'ar' && request.domains.includes('B')),
    false,
    'the original commits Arabic without ever loading translations for the newer page B',
  );
  console.log('LEGACY DEFECT REPRODUCED: language change committed without loading newly navigated page domains');
});
