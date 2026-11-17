import process from 'node:process';
import { createServer } from 'vite';
import { flattenMessages } from './lib/localization-completeness.mjs';

const requiredHistoryKeys = [
  'events.history.title',
  'events.history.subtitle',
  'events.history.from',
  'events.history.until',
  'events.history.rows',
  'events.history.applyFilters',
  'events.history.clearFilters',
  'events.history.metricTrends',
  'events.history.metricTrendsHelp',
  'events.history.average',
  'events.history.best',
  'events.history.samples',
  'events.history.latest',
  'events.history.leaderboards',
  'events.history.leaderboardsHelp',
  'events.history.noEvents',
  'events.history.noEvidence',
  'events.history.evidenceAria',
  'events.history.rallyAssignments',
  'events.history.assignments',
  'events.history.allianceResults',
  'events.history.participants',
  'events.history.governorCount',
  'events.history.representedAlliance',
  'events.history.noParticipants',
];

const server = await createServer({
  configFile: false,
  root: process.cwd(),
  server: { middlewareMode: true },
  appType: 'custom',
});
try {
  const { localeCodes } = await server.ssrLoadModule('/resources/js/localization/locales.ts');
  const { importDomainCatalogue } = await server.ssrLoadModule(
    '/resources/js/localization/registry.ts',
  );
  const { loadCatalogue } = await server.ssrLoadModule('/resources/js/localization/loader.ts');
  const english = new Set(Object.keys(flattenMessages(await loadCatalogue('events', 'en'))));
  if (!english.size) throw new Error('English Event localization catalogue is empty.');
  const failures = [];
  for (const locale of localeCodes.filter((code) => code !== 'en')) {
    const localized = new Set(
      Object.keys(flattenMessages((await importDomainCatalogue('events', locale)).default)),
    );
    const unknown = [...localized].filter((key) => !english.has(key));
    const missing = [...english].filter((key) => !localized.has(key));
    const missingHistory = requiredHistoryKeys.filter((key) => !localized.has(key));
    if (unknown.length || missing.length || missingHistory.length)
      failures.push({ locale, unknown, missing, missingHistory });
    console.log(
      `${locale}: ${localized.size}/${english.size} Event keys, including runtime extensions`,
    );
  }
  if (failures.length) {
    console.error(JSON.stringify(failures, null, 2));
    process.exitCode = 1;
  }
} finally {
  await server.close();
}
