import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

type TestLocale = 'en' | 'fr';
type Catalogue = { hello: string; notifications?: { eyebrow: string } };

function canonicalEnglishAccount(): Catalogue {
  // Use the actual merged PR3 catalogue rather than simulating its contents.
  const source = readFileSync('resources/js/localization/messages/account/en.ts', 'utf8');
  const code = ts.transpileModule(source, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
  }).outputText;
  const exports: Record<string, unknown> = {};
  runInNewContext(code, { exports });
  return (exports as { default: Catalogue }).default;
}

function loadIsolatedLoader(source: string) {
  const code = ts.transpileModule(source, {
    compilerOptions: {
      target: ts.ScriptTarget.ES2022,
      module: ts.ModuleKind.CommonJS,
    },
  }).outputText;
  let importCalls = 0;
  const exports: Record<string, unknown> = {};
  const requireMock = (specifier: string): unknown => {
    if (specifier === './locales') return { defaultLocale: 'en' };
    if (specifier === './governor-lifecycle-labels') return { governorLifecycleLabels: () => ({}) };
    if (specifier === './registry') return {
      importDomainCatalogue(_domain: string, locale: TestLocale) {
        importCalls++;
        if (importCalls === 1) return Promise.reject(new Error('simulated first import rejection'));
        return Promise.resolve({
          default: _domain === 'account' && locale === 'en'
            ? canonicalEnglishAccount()
            : { hello: locale === 'fr' ? 'Bonjour' : 'Hello' },
        });
      },
    };
    throw new Error('Unexpected dependency in isolated loader: ' + specifier);
  };
  runInNewContext(code, { exports, require: requireMock });
  const loader = exports as { loadCatalogue(domain: string, locale: TestLocale): Promise<Catalogue> };
  return { loader, importCalls: () => importCalls };
}

test('pre-fix loader never retries a previously rejected import promise (negative control)', async () => {
  const original = execFileSync('git', [
    'show',
    '7cf387b628bbbaeb81d10120340e559b46403bee:resources/js/localization/loader.ts',
  ], { encoding: 'utf8' });
  const old = loadIsolatedLoader(original);
  await assert.rejects(old.loader.loadCatalogue('core', 'fr'), /simulated first import rejection/);
  await assert.rejects(old.loader.loadCatalogue('core', 'fr'), /simulated first import rejection/);
  assert.equal(old.importCalls(), 1, 'original pending cache prevented second import attempt');
  console.log('LEGACY DEFECT REPRODUCED: rejected promise permanently blocks a later catalogue retry');
});

test('actual 7cf387b Account/en retry fails, while the current canonical Account loader succeeds', async () => {
  // Read the original source directly from the immutable Git object at test
  // time. No copy of the old loader is maintained by this test.
  const original = execFileSync('git', [
    'show',
    '7cf387b628bbbaeb81d10120340e559b46403bee:resources/js/localization/loader.ts',
  ], { encoding: 'utf8' });
  const current = readFileSync('resources/js/localization/loader.ts', 'utf8');
  const before = loadIsolatedLoader(original);
  await assert.rejects(before.loader.loadCatalogue('account', 'en'), /simulated first import rejection/);
  await assert.rejects(before.loader.loadCatalogue('account', 'en'), /simulated first import rejection/,
    'the actual 7cf387b loader must fail the required successful second Account import');
  assert.equal(before.importCalls(), 1, 'actual baseline permanently reuses the rejected Account promise');
  assert.throws(
    () => assert.equal(before.importCalls(), 2, 'successful retry requires a second Account import'),
    /successful retry requires a second Account import/,
  );

  const after = loadIsolatedLoader(current);
  await assert.rejects(after.loader.loadCatalogue('account', 'en'), /simulated first import rejection/);
  const recovered = await after.loader.loadCatalogue('account', 'en');
  assert.equal(recovered.notifications?.eyebrow, 'Recipient delivery');
  assert.equal(after.importCalls(), 2, 'fixed code invokes the registry again');
  console.log('REAL BASELINE ACCOUNT DEFECT: 7cf387b Account/en retry failed (1 import); current Account/en recovered after 2 imports');
});

test('fixed loader discards the rejection and retries successfully (positive control)', async () => {
  const fixed = loadIsolatedLoader(readFileSync('resources/js/localization/loader.ts', 'utf8'));
  await assert.rejects(fixed.loader.loadCatalogue('core', 'fr'), /simulated first import rejection/);
  const messages = await fixed.loader.loadCatalogue('core', 'fr');
  assert.equal(messages.hello, 'Bonjour');
  assert.equal(fixed.importCalls(), 2, 'fixed loader must issue a new import after the failure');
  console.log('FIX VERIFIED: failed locale import retried and the second attempt loaded successfully');
});


test('merged account canonical messages retry after a failed import without reloading the removed overlay', async () => {
  const source = readFileSync('resources/js/localization/loader.ts', 'utf8');
  assert.doesNotMatch(source, /communications-recipient-delivery-labels|communicationsRecipientDeliveryCatalogue/, 'PR3 English-only Account overlay must remain consolidated');
  assert.doesNotMatch(source, /transfer-evidence-labels|transfersCatalogue/, 'PR3 English-only transfer overlay must remain consolidated');
  const fixed = loadIsolatedLoader(source);
  await assert.rejects(fixed.loader.loadCatalogue('account', 'en'), /simulated first import rejection/);
  const messages = await fixed.loader.loadCatalogue('account', 'en');
  assert.equal(messages.notifications?.eyebrow, 'Recipient delivery');
  assert.equal(fixed.importCalls(), 2, 'canonical Account catalogue must retry after rejection');
  const cached = await fixed.loader.loadCatalogue('account', 'en');
  assert.equal(cached.notifications?.eyebrow, 'Recipient delivery');
  assert.equal(fixed.importCalls(), 2, 'resolved catalogue is cached after successful retry');
  console.log('MERGED ACCOUNT IMPORT RETRY VERIFIED: failed once, recovered canonical Recipient delivery message without overlay');
});

test('mutation control: removing loader finally cleanup makes canonical Account retry fail again', async () => {
  const source = readFileSync('resources/js/localization/loader.ts', 'utf8');
  const cleanup = [
    '    .finally(() => {',
    '      // Clear pending on both success and failure; a rejected import must be retryable.',
    '      pending.delete(key);',
    '    });',
  ].join('\n');
  assert.equal(source.split(cleanup).length - 1, 1, 'real loader must have one precise cleanup block');
  const mutant = loadIsolatedLoader(source.replace(cleanup, ';'));
  await assert.rejects(mutant.loader.loadCatalogue('account', 'en'), /simulated first import rejection/);
  await assert.rejects(
    mutant.loader.loadCatalogue('account', 'en'),
    /simulated first import rejection/,
    'without finally, the exact same Account retry must fail, not silently succeed',
  );
  assert.equal(mutant.importCalls(), 1, 'mutant must retain the rejected promise instead of importing twice');
  const clean = loadIsolatedLoader(source);
  await assert.rejects(clean.loader.loadCatalogue('account', 'en'), /simulated first import rejection/);
  const messages = await clean.loader.loadCatalogue('account', 'en');
  assert.equal(messages.notifications?.eyebrow, 'Recipient delivery');
  assert.equal(clean.importCalls(), 2);
  console.log('ACCOUNT RETRY MUTATION REJECTED: removing finally traps rejected promise; actual canonical Account source succeeds only with cleanup');
});
