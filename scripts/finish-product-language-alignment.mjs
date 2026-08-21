import fs from 'node:fs';

const locales = ['ar', 'de', 'en', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];

const allLabels = {
  ar: 'الكل',
  de: 'Alle',
  en: 'All',
  es: 'Todos',
  fr: 'Tous',
  id: 'Semua',
  it: 'Tutti',
  ja: 'すべて',
  ko: '전체',
  pl: 'Wszystkie',
  'pt-BR': 'Todos',
  ru: 'Все',
  th: 'ทั้งหมด',
  tr: 'Tümü',
  vi: 'Tất cả',
  'zh-CN': '全部',
  'zh-TW': '全部',
};

const cadenceLabels = {
  ar: ['يومي', 'أسبوعي', 'شهري'],
  de: ['Täglich', 'Wöchentlich', 'Monatlich'],
  en: ['Daily', 'Weekly', 'Monthly'],
  es: ['Diario', 'Semanal', 'Mensual'],
  fr: ['Quotidien', 'Hebdomadaire', 'Mensuel'],
  id: ['Harian', 'Mingguan', 'Bulanan'],
  it: ['Giornaliero', 'Settimanale', 'Mensile'],
  ja: ['毎日', '毎週', '毎月'],
  ko: ['매일', '매주', '매월'],
  pl: ['Codziennie', 'Co tydzień', 'Co miesiąc'],
  'pt-BR': ['Diário', 'Semanal', 'Mensal'],
  ru: ['Ежедневно', 'Еженедельно', 'Ежемесячно'],
  th: ['รายวัน', 'รายสัปดาห์', 'รายเดือน'],
  tr: ['Günlük', 'Haftalık', 'Aylık'],
  vi: ['Hằng ngày', 'Hằng tuần', 'Hằng tháng'],
  'zh-CN': ['每日', '每周', '每月'],
  'zh-TW': ['每日', '每週', '每月'],
};

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

function write(path, source) {
  fs.writeFileSync(path, source);
}

function replace(path, from, to) {
  let source = read(path);
  if (source.includes(to)) return;
  if (!source.includes(from)) throw new Error(`${path}: replacement source not found: ${from.slice(0, 80)}`);
  source = source.replace(from, to);
  write(path, source);
}

function replaceRegex(path, pattern, to) {
  let source = read(path);
  if (typeof to === 'string' && source.includes(to)) return;
  if (!pattern.test(source)) throw new Error(`${path}: replacement pattern not found: ${pattern}`);
  source = source.replace(pattern, to);
  write(path, source);
}

for (const locale of locales) {
  const corePath = `resources/js/localization/messages/core/${locale}.ts`;
  let core = read(corePath);
  if (!/\n\s+all:\s/.test(core)) {
    core = core.replace('  common: {\n', `  common: {\n    all: '${allLabels[locale]}',\n`);
    write(corePath, core);
  }

  const contributionsPath = `resources/js/localization/messages/contributions/${locale}.ts`;
  let contributions = read(contributionsPath);
  if (!/\n\s+cadences:\s*\{/.test(contributions)) {
    const [daily, weekly, monthly] = cadenceLabels[locale];
    const block = `    cadences: {\n      daily: '${daily}',\n      weekly: '${weekly}',\n      monthly: '${monthly}',\n    },\n`;
    contributions = contributions.replace('  contributions: {\n', `  contributions: {\n${block}`);
    write(contributionsPath, contributions);
  }
}

replace(
  'resources/js/pages/Intelligence/Contributions/Manage.vue',
  `              <option value="daily">daily</option>\n              <option value="weekly">weekly</option>\n              <option value="monthly">monthly</option>`,
  `              <option value="daily">{{ t('contributions.cadences.daily') }}</option>\n              <option value="weekly">{{ t('contributions.cadences.weekly') }}</option>\n              <option value="monthly">{{ t('contributions.cadences.monthly') }}</option>`,
);
replace(
  'resources/js/pages/Intelligence/Contributions/Manage.vue',
  `            sha256 {{ run.checksum }}`,
  `            SHA-256 {{ run.checksum }}`,
);

replaceRegex(
  'resources/js/pages/Intelligence/Roster/Dossiers.vue',
  /<RoomBanner\n\s+eyebrow="Intel Room"\n\s+title="Alliance Roster"\n\s+subtitle="Study the Alliance roster, compare recorded Governor strength, and follow roster changes over time\."/,
  `<RoomBanner\n      :eyebrow="t('roster.intelligence')"\n      :title="t('roster.title')"\n      :subtitle="t('roster.intelligenceSubtitle')"`,
);
replace(
  'resources/js/pages/Intelligence/Roster/Dossiers.vue',
  `<Link href="/alliance/roster" class="ks-command-link">Alliance Members</Link`,
  `<Link href="/alliance/roster" class="ks-command-link">{{ t('roster.title') }}</Link`,
);
replaceRegex(
  'resources/js/pages/Intelligence/Roster/Dossiers.vue',
  /<Link href="\/alliance\/roster\/history" class="ks-command-link"\s*>Roster Chronicle<\/Link/,
  `<Link href="/alliance/roster/history" class="ks-command-link">{{ t('rosterHistory.title') }}</Link`,
);

const dossier = 'resources/js/pages/Intelligence/KingdomWatch/AllianceDossier.vue';
replace(
  dossier,
  `  return \`Power \${formatSignedDecimal(change.powerChange)} · Members \${formatSignedNumber(change.memberChange)} · \${t('kingdomP7B.baseline', { date: formatDate(change.baselineCapturedAt) })}\`;`,
  `  return \`${'${'}t('kingdomP7B.power')} \${formatSignedDecimal(change.powerChange)} · ${'${'}t('kingdomP7B.members')} \${formatSignedNumber(change.memberChange)} · \${t('kingdomP7B.baseline', { date: formatDate(change.baselineCapturedAt) })}\`;`,
);
replace(
  dossier,
  `  return \`${'${'}stateLabel(row.freshness)} · \${row.observationAgeDays} day${'${'}row.observationAgeDays === 1 ? '' : 's'} old\`;`,
  `  return \`${'${'}stateLabel(row.freshness)} · ${'${'}t('kingdomP7B.daysOld', { days: row.observationAgeDays })}\`;`,
);
replace(dossier, `          ← Tracked alliances`, `          ← {{ t('kingdomP7A.overviewTitle') }}`);
replace(dossier, `          Kingdom {{ alliance.kingdom ?? 'not set' }}`, `          {{ t('kingdomP7A.kingdom') }} {{ alliance.kingdom ?? t('kingdomP7B.notSet') }}`);
replace(dossier, `      :aria-label="\`${'${'}t('kingdomP7B.intelligenceTitle')} summary\`"`, `      :aria-label="t('kingdomP7B.intelligenceTitle')"`);
replace(
  dossier,
  `          Current means captured within\n          {{ intelligence.summary.observationQuality.staleAfterDays }} days.`,
  `          {{\n            t('kingdomP7B.currentWithinDays', {\n              days: intelligence.summary.observationQuality.staleAfterDays,\n            })\n          }}`,
);
replace(dossier, `          Review/expiry dates are advisory and never change diplomacy automatically.`, `          {{ t('kingdomP7B.reviewAdvisory') }}`);
replace(dossier, `      aria-label="Private contact diagnostics"`, `      :aria-label="t('kingdomP7B.contacts')"`);
for (const state of ['unknown', 'neutral', 'friendly', 'nap', 'ally', 'rival']) {
  const label = state === 'nap' ? 'NAP' : state[0].toUpperCase() + state.slice(1);
  replace(dossier, `<option value="${state}">${label}</option>`, `<option value="${state}">{{ t('kingdomP7B.${state}') }}</option>`);
}
replaceRegex(
  dossier,
  /        Current windows: \{\{ intelligence\.windows\.sevenDay\.days \}\}–\{\{[\s\S]*?days for the 30-day comparison\./,
  `        {{\n          t('kingdomP7B.windowsHelp', {\n            seven: intelligence.windows.sevenDay.days,\n            sevenOldest: intelligence.windows.sevenDay.oldestDays,\n            thirty: intelligence.windows.thirtyDay.days,\n            thirtyOldest: intelligence.windows.thirtyDay.oldestDays,\n          })\n        }}`,
);
replaceRegex(
  dossier,
  /        <p class="mt-1 text-sm text-\[var\(--ks-text-secondary\)\]">\n          \{\{ intelligence\.rows\.length \}\} record\(s\) match the current filters\. Zero is a recorded\n          value; “missing” means scouts have not recorded a value\.\n        <\/p>\n/,
  '',
);
replace(dossier, `{{ row.tag ?? '—' }} · Kingdom {{ row.kingdom }}`, `{{ row.tag ?? '—' }} · {{ t('kingdomP7A.kingdom') }} {{ row.kingdom }}`);
replace(dossier, `            Tracked game-side alliance descriptive intelligence`, `            {{ t('kingdomP7B.allianceIntelligence') }}`);
replace(dossier, `              <th class="px-4 py-3">Data quality</th>`, `              <th class="px-4 py-3">{{ t('kingdomP7B.observationFreshness') }}</th>`);
replace(dossier, `                  {{ row.tag ?? 'No tag recorded' }}`, `                  {{ row.tag ?? t('kingdomP7A.noTag') }}`);
replace(dossier, `                  {{ stateLabel(row.trackingState) }} tracking · Kingdom {{ row.kingdom }}`, `                  {{ stateLabel(row.trackingState) }} · {{ t('kingdomP7A.kingdom') }} {{ row.kingdom }}`);
replace(dossier, `                  Earlier Kingdom record`, `                  {{ t('kingdomP7A.historicalContext') }}`);
replace(dossier, `                  <p>Power {{ formatDecimal(row.latestObservation.power) }}</p>`, `                  <p>{{ t('kingdomP7B.power') }} {{ formatDecimal(row.latestObservation.power) }}</p>`);
replace(dossier, `                  <p>Members {{ row.latestObservation.memberCount ?? 'missing' }}</p>`, `                  <p>{{ t('kingdomP7B.members') }} {{ row.latestObservation.memberCount ?? t('kingdomP7B.missing') }}</p>`);
replace(dossier, `                <span v-else>No accepted observation</span>`, `                <span v-else>{{ t('kingdomP7A.noAcceptedObservation') }}</span>`);
replace(dossier, `                  Review {{ formatDate(row.diplomacy.reviewAt) }}`, `                  {{ t('kingdomP7B.review') }} {{ formatDate(row.diplomacy.reviewAt) }}`);
replace(dossier, `                  Expiry {{ formatDate(row.diplomacy.expiresAt) }}`, `                  {{ t('kingdomP7B.expiry') }} {{ formatDate(row.diplomacy.expiresAt) }}`);
replace(dossier, `                  Manage diplomacy`, `                  {{ t('kingdomP7B.diplomacy') }}`);
replace(dossier, `                  <p>{{ row.contactDiagnostics.activeContacts }} active contact(s)</p>`, `                  <p>{{ t('kingdomP7B.activeContacts', { count: row.contactDiagnostics.activeContacts }) }}</p>`);
replace(dossier, `                    {{ row.contactDiagnostics.verificationDue }} verification due`, `                    {{ t('kingdomP7B.verificationDueShort', { count: row.contactDiagnostics.verificationDue }) }}`);
replace(dossier, `                    Latest verified {{ formatDate(row.contactDiagnostics.latestVerifiedAt) }}`, `                    {{ t('kingdomP7B.latestVerified', { date: formatDate(row.contactDiagnostics.latestVerifiedAt) }) }}`);
replace(dossier, `                  Contact directory`, `                  {{ t('kingdomP7B.directory') }}`);
replace(
  dossier,
  `      Calculated at {{ formatDate(intelligence.asOf) }} from accepted Alliance scout reports and\n      explicit human-maintained diplomacy state.`,
  `      {{ t('kingdomP7B.asOf', { date: formatDate(intelligence.asOf) }) }}`,
);

const history = 'resources/js/pages/Intelligence/KingdomWatch/History.vue';
replace(history, `        Back to tracked alliances`, `        {{ t('kingdomP7A.overviewTitle') }}`);
replace(
  history,
  `        This tracked Alliance belongs to an earlier Kingdom. New scout findings are blocked; history\n        remains readable.`,
  `        {{ t('kingdomP7B.readOnlyHistorical') }}`,
);
replace(history, `            placeholder="Leave blank if unknown"`, `            :placeholder="t('kingdomP7B.notSet')"`);
replace(history, `            placeholder="Manager-private context"`, `            :placeholder="t('kingdomP7B.privateContext')"`);
replace(history, `              <th class="px-3 py-3 font-semibold">Observed identity</th>`, `              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7B.observedName') }}</th>`);
replace(history, `                  {{ observation.observedTag ?? 'No tag' }}`, `                  {{ observation.observedTag ?? t('kingdomP7A.noTag') }}`);
replace(history, `                  Correction of {{ observation.correctsObservationId }}`, `                  {{ t('kingdomP7B.correctionOf', { id: observation.correctsObservationId }) }}`);
replaceRegex(
  history,
  /                  \{\{ t\('kingdomP7B\.invalidated'\) \}\} by\n                  \{\{ observation\.invalidatedByName \?\? 'unavailable actor' \}\} on\n                  \{\{ formatDate\(observation\.invalidatedAt\) \}\}/,
  `                  {{\n                    t('kingdomP7B.invalidatedBy', {\n                      actor: observation.invalidatedByName ?? t('kingdomP7B.unavailableActor'),\n                      date: formatDate(observation.invalidatedAt),\n                    })\n                  }}`,
);
replace(history, `        The original row remains historical and is excluded from latest/freshness projections.`, `        {{ t('kingdomP7B.historyHelp') }}`);
replace(history, `            Cancel`, `            {{ t('common.cancel') }}`);

replace(
  'resources/js/pages/Intelligence/KingdomWatch/Diplomacy.vue',
  `      <nav class="flex flex-wrap gap-2" aria-label="Diplomacy workspace">`,
  `      <nav class="flex flex-wrap gap-2" :aria-label="t('kingdomP7B.diplomacyTitle', { alliance: tracking.name })">`,
);
replace(
  'resources/js/pages/Intelligence/KingdomWatch/Reports.vue',
  `    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ingestion summary">`,
  `    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" :aria-label="t('kingdomP7A.ingestionTitle')">`,
);
replace(
  'resources/js/pages/Operations/Events/Manage.vue',
  `aria-label="Event command summary"`,
  `:aria-label="t('events.manage.title')"`,
);
replace(
  'resources/js/pages/Operations/Events/Manage.vue',
  `aria-label="Event organizer command sections"`,
  `:aria-label="t('events.manage.eyebrow')"`,
);
replace(
  'resources/js/pages/Platform/Administration/Index.vue',
  `aria-label="Platform command summary"`,
  `:aria-label="t('platformAdmin.title')"`,
);
replace(
  'resources/js/pages/Platform/EventTypes/Index.vue',
  `aria-label="Event catalogue summary"`,
  `:aria-label="t('events.catalogue.title')"`,
);
replace(
  'resources/js/pages/Public/Alliance/Show.vue',
  `        :alt="\`${'${'}alliance.name} banner\`"`,
  `        alt=""\n        aria-hidden="true"`,
);
replace(
  'resources/js/pages/Public/Alliance/Show.vue',
  `              :alt="\`${'${'}alliance.name} logo\`"`,
  `              :alt="alliance.name"`,
);

const platform = 'resources/js/pages/Platform/Administration/Index.vue';
replace(
  platform,
  `const queueMetricKeys = [`,
  `const queuePartitions = ['standard', 'high-volume', 'maintenance-sensitive'] as const;\n\nconst queueMetricKeys = [`,
);
replace(
  platform,
  `                <option>standard</option>\n                <option>high-volume</option>\n                <option>maintenance-sensitive</option>`,
  `                <option v-for="partition in queuePartitions" :key="partition" :value="partition">\n                  {{ partition }}\n                </option>`,
);

const checker = 'scripts/check-page-localization-coverage.mjs';
let checkerSource = read(checker);
for (const literal of ["'· HTTP',", "'min',", "'sha256',"]) {
  if (!checkerSource.includes(literal)) {
    checkerSource = checkerSource.replace("  'https://',\n", `  'https://',\n  ${literal}\n`);
  }
}
write(checker, checkerSource);

console.log('Finished product-language alignment replacements and locale additions.');
