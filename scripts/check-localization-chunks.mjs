import { readFile, readdir, stat } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const manifestPath = path.resolve('public/build/manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const entries = Object.values(manifest);
const sources = new Set(entries.map((entry) => entry.src).filter(Boolean));

const pageSources = [...sources].filter((source) => source.startsWith('resources/js/pages/'));
const localeSources = [...sources].filter((source) =>
  source.startsWith('resources/js/localization/messages/'),
);

async function collectTypeScriptSources(directory) {
  const result = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      result.push(...(await collectTypeScriptSources(absolute)));
    } else if (entry.isFile() && entry.name.endsWith('.ts')) {
      result.push(path.relative(process.cwd(), absolute).split(path.sep).join('/'));
    }
  }
  return result;
}

if (pageSources.length < 10) {
  throw new Error(`Expected lazy Inertia page chunks; found only ${pageSources.length}.`);
}

const expectedLocaleSources = new Set(
  await collectTypeScriptSources(path.resolve('resources/js/localization/messages')),
);
const missingLocaleSources = [...expectedLocaleSources].filter((source) => !sources.has(source));
const unexpectedLocaleSources = localeSources.filter((source) => !expectedLocaleSources.has(source));

if (missingLocaleSources.length || unexpectedLocaleSources.length) {
  throw new Error(
    `Localization chunk mismatch. Missing: ${missingLocaleSources.join(', ') || 'none'}. Unexpected: ${unexpectedLocaleSources.join(', ') || 'none'}.`,
  );
}

const appEntry = entries.find((entry) => entry.src === 'resources/js/app.ts');
if (!appEntry?.file) throw new Error('Vite manifest is missing the app entry.');
if (!Array.isArray(appEntry.dynamicImports) || appEntry.dynamicImports.length === 0) {
  throw new Error('The app entry has no dynamic imports; page/domain splitting is not active.');
}

const appStats = await stat(path.resolve('public/build', appEntry.file));
console.log(
  `Localization chunks: ${localeSources.length}; page chunks: ${pageSources.length}; app entry: ${Math.round(appStats.size / 1024)} KiB.`,
);
