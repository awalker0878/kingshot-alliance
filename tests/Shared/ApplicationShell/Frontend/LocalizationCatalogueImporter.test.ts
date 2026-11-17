import assert from 'node:assert/strict';
import test from 'node:test';
import { createCatalogueImporter } from '../../../../resources/js/localization/catalogue-importer.ts';

const path = './messages/public/fr.ts';
const source = 'resources/js/localization/messages/public/fr.ts';
const metadata = {
  'resources/js/app.ts': { file: 'assets/app-current.js' },
  [source]: { file: 'assets/fr-current.js' },
};

function fixture() {
  const requests: string[] = [];
  let reads = 0;
  const ports = {
    enabled: () => true,
    baseUrl: () => 'https://example.test/build/',
    currentModuleUrl: () => 'https://example.test/build/assets/app-current.js',
    manifest: async () => { reads++; return metadata; },
    importUrl: async (url: string) => { requests.push(url); return 'Bonjour'; },
  };
  const modules = { [path]: async () => { throw new Error('cached browser module failure'); } };
  return { ports, requests, reads: () => reads, importer: () => createCatalogueImporter(modules, ports) };
}

test('a native cached rejection recovers with a fresh URL from the running build', async () => {
  const f = fixture(), load = f.importer();
  await assert.rejects(load(path), /cached browser module failure/);
  assert.equal(f.reads(), 0, 'successful first loads do not fetch metadata');
  assert.equal(await load(path), 'Bonjour');
  assert.deepEqual(f.requests, ['https://example.test/build/assets/fr-current.js?locale-retry=1']);
});

test('each failed retry gets a distinct module URL and reuses successful metadata', async () => {
  const f = fixture();
  f.ports.importUrl = async url => {
    f.requests.push(url);
    if (f.requests.length === 1) throw new Error('still offline');
    return 'Bonjour';
  };
  const load = f.importer();
  await assert.rejects(load(path));
  await assert.rejects(load(path), /still offline/);
  assert.equal(await load(path), 'Bonjour');
  assert.deepEqual(f.requests.map(url => new URL(url).search), ['?locale-retry=1', '?locale-retry=2']);
  assert.equal(f.reads(), 1);
});

test('metadata fetch failure is discarded and can recover on the next attempt', async () => {
  const f = fixture();
  let reads = 0;
  f.ports.manifest = async () => { if (++reads === 1) throw new Error('metadata offline'); return metadata; };
  const load = f.importer();
  await assert.rejects(load(path));
  await assert.rejects(load(path), /metadata offline/);
  assert.equal(await load(path), 'Bonjour');
  assert.equal(reads, 2);
});

test('a manifest that no longer contains the running module cannot mix releases', async () => {
  const f = fixture();
  f.ports.manifest = async () => ({ ...metadata, 'resources/js/app.ts': { file: 'assets/app-new.js' } });
  const load = f.importer();
  await assert.rejects(load(path));
  await assert.rejects(load(path), /application build changed/);
  assert.deepEqual(f.requests, []);
});

test('metadata cannot redirect catalogue imports outside built JavaScript assets', async () => {
  for (const file of ['https://other.test/code.js', '../code.js', 'assets/../code.js', 'assets/code.js?x=1']) {
    const f = fixture();
    f.ports.manifest = async () => ({ ...metadata, [source]: { file } });
    const load = f.importer();
    await assert.rejects(load(path));
    await assert.rejects(load(path), /Invalid localization chunk/);
    assert.deepEqual(f.requests, []);
  }
});

test('successful normal imports retain the lazy import path without fetching metadata', async () => {
  const f = fixture();
  const load = createCatalogueImporter({ [path]: async () => 'Bonjour' }, f.ports);
  assert.equal(await load(path), 'Bonjour');
  assert.equal(f.reads(), 0);
  assert.deepEqual(f.requests, []);
});

test('server and development imports can retry without any browser access', async () => {
  let calls = 0;
  const f = fixture();
  f.ports.enabled = () => false;
  const load = createCatalogueImporter({ [path]: async () => {
    if (++calls === 1) throw new Error('failed');
    return 'Bonjour';
  } }, f.ports);
  await assert.rejects(load(path));
  assert.equal(await load(path), 'Bonjour');
  assert.equal(f.reads(), 0);
});
