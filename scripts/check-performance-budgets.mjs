import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const buildRoot = path.resolve('public/build');
const manifest = JSON.parse(await readFile(path.join(buildRoot, 'manifest.json'), 'utf8'));
const budgets = JSON.parse(await readFile(path.resolve('performance-budgets.json'), 'utf8'));
const entries = Object.values(manifest);
const appEntry = entries.find((entry) => entry.src === 'resources/js/app.ts');

if (!appEntry?.file) throw new Error('Vite manifest is missing the application entry.');

async function bytes(file) {
  return (await stat(path.join(buildRoot, file))).size;
}

const initialFiles = new Set();
async function collectInitial(entry) {
  if (!entry?.file || initialFiles.has(entry.file)) return;
  initialFiles.add(entry.file);
  for (const imported of entry.imports ?? []) await collectInitial(manifest[imported]);
}

await collectInitial(appEntry);
const initialJavaScriptBytes = (
  await Promise.all([...initialFiles].filter((file) => file.endsWith('.js')).map(bytes))
).reduce((total, size) => total + size, 0);
const appEntryBytes = await bytes(appEntry.file);

const pageAssets = entries.filter(
  (entry) => entry.src?.startsWith('resources/js/pages/') && entry.file?.endsWith('.js'),
);
const measuredPages = await Promise.all(
  pageAssets.map(async (entry) => ({ source: entry.src, file: entry.file, size: await bytes(entry.file) })),
);
const largestPage = measuredPages.sort((left, right) => right.size - left.size)[0];

const stylesheetFiles = new Set(entries.flatMap((entry) => entry.css ?? []));
const measuredStylesheets = await Promise.all(
  [...stylesheetFiles].map(async (file) => ({ file, size: await bytes(file) })),
);
const largestStylesheet = measuredStylesheets.sort((left, right) => right.size - left.size)[0];

const measurements = [
  ['initial JavaScript', initialJavaScriptBytes, budgets.initialJavaScriptBytes, [...initialFiles].join(', ')],
  ['application entry', appEntryBytes, budgets.appEntryBytes, appEntry.file],
  ['largest page chunk', largestPage?.size ?? 0, budgets.largestPageChunkBytes, largestPage?.source ?? 'none'],
  ['largest stylesheet', largestStylesheet?.size ?? 0, budgets.largestStylesheetBytes, largestStylesheet?.file ?? 'none'],
];

const failures = measurements.filter(([, actual, budget]) => actual > budget);
for (const [label, actual, budget, detail] of measurements) {
  const state = actual <= budget ? 'PASS' : 'FAIL';
  console.log(`[${state}] ${label}: ${Math.round(actual / 1024)} KiB / ${Math.round(budget / 1024)} KiB (${detail})`);
}

if (failures.length > 0) {
  throw new Error('Production asset performance budget exceeded. Split or remove the responsible code before raising a reviewed budget.');
}
