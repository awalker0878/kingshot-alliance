import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import {
  readLocaleRegistry,
  validateLocaleRegistry,
} from '../../../../scripts/localization/locale-registry.mjs';
import {
  locales,
  defaultLocale,
  normalizeLocale,
} from '../../../../resources/js/localization/locales.ts';

test('runtime metadata and declared aliases agree with the authored registry', () => {
  const registry = readLocaleRegistry();
  assert.equal(defaultLocale, registry.defaultLocale);
  assert.deepEqual(
    locales,
    registry.locales.map(({ code, nativeName, englishName, direction }) => ({
      code,
      nativeName,
      englishName,
      direction,
    })),
  );
  for (const locale of registry.locales) {
    for (const alias of locale.aliases) {
      assert.equal(normalizeLocale(alias.tag), locale.code);
      assert.equal(normalizeLocale(alias.tag + '-xx'), alias.descendants ? locale.code : null);
    }
  }
});

test('ambiguous aliases, duplicate codes and unknown defaults cannot be generated', () => {
  const overlap = readLocaleRegistry();
  overlap.locales
    .find(({ code }) => code === 'zh-CN')
    .aliases.find(({ tag }) => tag === 'zh').descendants = true;
  assert.throws(() => validateLocaleRegistry(overlap), /Ambiguous locale aliases/);
  const duplicate = readLocaleRegistry();
  duplicate.locales.push(duplicate.locales[0]);
  assert.throws(() => validateLocaleRegistry(duplicate), /Duplicate locale/);
  const missing = readLocaleRegistry();
  missing.defaultLocale = 'zz';
  assert.throws(() => validateLocaleRegistry(missing), /Default must be/);
});

test('generation is deterministic and check mode rejects stale PHP and TS outputs', () => {
  const root = mkdtempSync(path.join(tmpdir(), 'locale-generation-'));
  const generator = path.resolve('scripts/localization/generate-locale-definitions.mjs');
  const files = [
    'resources/js/localization/locale-definitions.generated.ts',
    'app/Shared/Infrastructure/Http/LocaleDefinitions.php',
  ];
  const run = (...args: string[]) =>
    spawnSync(process.execPath, [generator, ...args], { cwd: root, encoding: 'utf8' });
  try {
    for (const file of files) mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
    assert.equal(run().status, 0);
    for (const file of files)
      assert.equal(readFileSync(path.join(root, file), 'utf8'), readFileSync(file, 'utf8'));
    assert.equal(run('--check').status, 0);
    for (const file of files) {
      writeFileSync(path.join(root, file), 'stale definitions');
      const check = run('--check');
      assert.equal(check.status, 1);
      assert.ok(check.stderr.includes(file));
      assert.equal(run().status, 0);
    }
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
