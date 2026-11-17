import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import ts from 'typescript';
import { createLocaleLoadCoordinator } from '../../../../resources/js/localization/async-coordinator.ts';

type Locale = 'en' | 'fr';
type Domain = 'core' | 'A' | 'B' | 'C';

async function failedNavigationProbe(factory = createLocaleLoadCoordinator) {
  const loads: string[][] = [];
  const coordinator = factory<Locale, Domain>({
    initialLocale: 'en', coreDomain: 'core', domainsForPage: (name) => [name as Domain],
    async load(locale, domains) {
      loads.push([locale, ...domains]);
      if (locale === 'en' && domains.includes('B')) throw new Error('B chunk failed');
    },
    commit() {},
  });
  await coordinator.ensurePageDomains('A');
  await assert.rejects(coordinator.ensurePageDomains('B'), /B chunk failed/);
  await coordinator.setLocale('fr');
  return { loads, coordinator };
}

test('A active, B fails, then French loads A before committing', async () => {
  const { loads, coordinator } = await failedNavigationProbe();
  assert.deepEqual(coordinator.activeDomains(), ['core', 'A']);
  assert.deepEqual(loads.at(-1), ['fr', 'core', 'A']);
});

test('actual baef44be coordinator reproduces the new failed-navigation regression', async () => {
  const source = execFileSync('git', ['show', 'baef44befec7f3e3a3a46ea311b8e799e1022500:resources/js/localization/async-coordinator.ts'], { encoding: 'utf8' });
  const exports: Record<string, unknown> = {};
  runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, { exports });
  const { loads, coordinator } = await failedNavigationProbe(exports.createLocaleLoadCoordinator as typeof createLocaleLoadCoordinator);
  assert.deepEqual([...coordinator.activeDomains()], ['core', 'A']);
  assert.deepEqual(loads.at(-1), ['fr', 'core', 'B']);
});

function deferred() {
  let resolve!: () => void;
  let reject!: (error: Error) => void;
  const promise = new Promise<void>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

function pendingHarness() {
  const requests: Array<{ locale: Locale; domains: Domain[]; gate: ReturnType<typeof deferred> }> = [];
  const commits: Locale[] = [];
  const coordinator = createLocaleLoadCoordinator<Locale, Domain>({
    initialLocale: 'en', coreDomain: 'core', domainsForPage: name => [name as Domain],
    load(locale, domains) { const gate = deferred(); requests.push({ locale, domains, gate }); return gate.promise; },
    commit(locale) { commits.push(locale); },
  });
  return { coordinator, requests, commits };
}

async function activeA() {
  const h = pendingHarness();
  const first = h.coordinator.ensurePageDomains('A');
  h.requests[0]!.gate.resolve();
  await first;
  return h;
}

test('pending B does not replace displayed A, and French covers both until B fails', async () => {
  const h = await activeA();
  const pageB = h.coordinator.preparePageDomains('B');
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'A']);
  const french = h.coordinator.setLocale('fr');
  assert.deepEqual(h.requests[2]!.domains, ['core', 'A', 'B']);
  h.requests[2]!.gate.resolve();
  await french;
  h.requests[1]!.gate.reject(new Error('B chunk failed'));
  await assert.rejects(pageB.ready, /B chunk failed/);
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'A']);
  assert.equal(h.coordinator.getLocale(), 'fr');
});

test('late B failure cannot overwrite newer C or its French domains', async () => {
  const h = await activeA();
  const pageB = h.coordinator.ensurePageDomains('B');
  const pageC = h.coordinator.ensurePageDomains('C');
  h.requests[2]!.gate.resolve();
  await pageC;
  h.requests[1]!.gate.reject(new Error('old B failure'));
  await assert.rejects(pageB, /old B failure/);
  const french = h.coordinator.setLocale('fr');
  assert.deepEqual(h.requests[3]!.domains, ['core', 'C']);
  h.requests[3]!.gate.resolve();
  await french;
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'C']);
});

test('cancellation immediately drops B intent while its import is still pending', async () => {
  const h = await activeA();
  const controller = new AbortController();
  const pageB = h.coordinator.preparePageDomains('B', controller.signal);
  controller.abort();
  const french = h.coordinator.setLocale('fr');
  assert.deepEqual(h.requests[2]!.domains, ['core', 'A']);
  h.requests[2]!.gate.resolve();
  await french;
  h.requests[1]!.gate.resolve();
  await assert.rejects(pageB.ready, /cancelled/);
  pageB.accept();
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'A']);
});

test('cancellation after translations load but before component acceptance retains A', async () => {
  const h = await activeA();
  const controller = new AbortController();
  const pageB = h.coordinator.preparePageDomains('B', controller.signal);
  h.requests[1]!.gate.resolve();
  await pageB.ready;
  controller.abort();
  pageB.accept();
  const french = h.coordinator.setLocale('fr');
  assert.deepEqual(h.requests[2]!.domains, ['core', 'A']);
  h.requests[2]!.gate.resolve();
  await french;
});

test('failed B can retry after a language change and is activated only when accepted', async () => {
  const h = await activeA();
  const failed = h.coordinator.preparePageDomains('B');
  h.requests[1]!.gate.reject(new Error('B failed'));
  await assert.rejects(failed.ready, /B failed/);
  const french = h.coordinator.setLocale('fr');
  h.requests[2]!.gate.resolve();
  await french;
  const retry = h.coordinator.preparePageDomains('B');
  assert.equal(h.requests[3]!.locale, 'fr');
  h.requests[3]!.gate.resolve();
  await retry.ready;
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'A']);
  retry.accept();
  assert.deepEqual(h.coordinator.activeDomains(), ['core', 'B']);
});

test('a pending locale load retries A if its now-cancelled B chunk fails', async () => {
  const h = await activeA();
  const pageB = h.coordinator.preparePageDomains('B');
  const french = h.coordinator.setLocale('fr');
  pageB.cancel();
  h.requests[2]!.gate.reject(new Error('cancelled B/French failed'));
  await Promise.resolve();
  assert.deepEqual(h.requests[3]!.domains, ['core', 'A']);
  h.requests[3]!.gate.resolve();
  await french;
  h.requests[1]!.gate.resolve();
  await assert.rejects(pageB.ready, /cancelled/);
  assert.deepEqual(h.commits, ['fr']);
});
