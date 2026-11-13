import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const reader = fileURLToPath(new URL('./localization/read-php-catalogue.php', import.meta.url));
const sha256 = (data) => createHash('sha256').update(data).digest('hex');

export function readPhpCatalogue(file, group) {
  assert.match(group, /^[a-z][a-z0-9_-]*$/);
  return JSON.parse(
    execFileSync('php', [reader, file, group], { encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 }),
  );
}

export function exportPhpKeys(output, ref = null, includeFramework = false) {
  if (ref !== null) assert.match(ref, /^[a-f0-9]{40}$/, 'Use an immutable source revision');
  assert.ok(
    !(ref && includeFramework),
    'Pinned project source cannot borrow current vendor catalogues',
  );
  const temporary = ref ? mkdtempSync(path.join(tmpdir(), 'localization-php-source-')) : null;
  const root = temporary ?? process.cwd();
  try {
    if (temporary) {
      const archive = execFileSync('git', ['archive', ref, 'lang'], {
        maxBuffer: 32 * 1024 * 1024,
      });
      execFileSync('tar', ['-x', '-C', temporary], { input: archive });
    }
    const catalogues = {},
      files = {},
      legacyAccounts = {};
    for (const locale of readdirSync(path.join(root, 'lang'), { withFileTypes: true })
      .filter((entry) => entry.isDirectory())
      .map((entry) => entry.name)
      .sort()) {
      catalogues[locale] = {};
      files[locale] = {};
      for (const file of readdirSync(path.join(root, 'lang', locale))
        .filter((file) => file.endsWith('.php'))
        .sort()) {
        const group = file.slice(0, -4);
        const relative = path.join('lang', locale, file);
        const values = readPhpCatalogue(path.join(root, relative), group);
        catalogues[locale][group] = Object.fromEntries(
          Object.entries(values).sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0)),
        );
        files[locale][group] = {
          path: relative,
          sha256: sha256(readFileSync(path.join(root, relative))),
        };
        if (locale === 'en' && group === 'accounts') {
          for (const [index, [key, source]] of Object.entries(values).entries())
            legacyAccounts['accounts.php#' + (index + 1)] = {
              key,
              source,
              sourceSha256: sha256(source),
            };
        }
      }
    }
    if (includeFramework) {
      // Only a current export may use installed framework sources. Record their
      // exact bytes and the lock hash so release scope can bind that provenance.
      const directory = 'vendor/laravel/framework/src/Illuminate/Translation/lang/en';
      for (const file of readdirSync(directory)
        .filter((file) => file.endsWith('.php'))
        .sort()) {
        const group = file.slice(0, -4);
        const relative = path.join(directory, file);
        const vendor = readPhpCatalogue(relative, group);
        catalogues.en[group] = { ...vendor, ...catalogues.en[group] };
        files.en[group] = {
          project: files.en[group] ?? null,
          vendorPath: relative,
          vendorSha256: sha256(readFileSync(relative)),
        };
      }
    }
    const result = {
      version: 1,
      sourceRef: ref ?? 'working-tree',
      identity: 'Laravel group.dotted.key',
      frameworkSourcesIncluded: includeFramework,
      composerLockSha256: includeFramework ? sha256(readFileSync('composer.lock')) : null,
      catalogues,
      files,
      legacyAccounts,
      note: 'Legacy ordinals are a source-revision bridge only. New review claims use stable keys. Missing English framework source cannot be approved.',
    };
    mkdirSync(path.dirname(output), { recursive: true });
    writeFileSync(output, JSON.stringify(result, null, 2) + '\n');
    return result;
  } finally {
    if (temporary) rmSync(temporary, { recursive: true, force: true });
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  const result = exportPhpKeys(
    process.argv[2] ?? '.translation-pr6-current/server-php-stable-keys.json',
    process.argv[3]?.startsWith('--') ? null : (process.argv[3] ?? null),
    process.argv.includes('--framework'),
  );
  console.log(
    `Stable PHP catalogue export: ${Object.keys(result.catalogues).length} locales; ${Object.keys(result.legacyAccounts).length} English account keys; framework source included: ${result.frameworkSourcesIncluded}.`,
  );
}
