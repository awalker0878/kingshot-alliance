import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const manifestPath = path.resolve('public/build/manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const entries = Object.values(manifest);
const sources = new Set(entries.map((entry) => entry.src).filter(Boolean));

const pageSources = [...sources].filter((source) => source.startsWith('resources/js/pages/'));
const localeSources = [...sources].filter((source) =>
  source.startsWith('resources/js/localization/messages/'),
);

if (pageSources.length < 10) {
  throw new Error(`Expected lazy Inertia page chunks; found only ${pageSources.length}.`);
}

if (localeSources.length !== 14 * 17) {
  throw new Error(`Expected 238 domain/locale chunks; found ${localeSources.length}.`);
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
