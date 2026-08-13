import { mkdir, readFile, readdir, rename, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

import { createServer } from 'vite';

const root = process.cwd();
const messagesRoot = path.join(root, 'resources/js/localization/messages');
const generatedRoot = path.join(root, 'resources/js/localization/messages.generated');
const localeCodes = [
  'en',
  'ar',
  'de',
  'es',
  'fr',
  'id',
  'it',
  'ja',
  'ko',
  'pl',
  'pt-BR',
  'ru',
  'th',
  'tr',
  'vi',
  'zh-CN',
  'zh-TW',
];
const domains = [
  'core',
  'auth',
  'account',
  'alliance',
  'events',
  'roster',
  'contributions',
  'recruitment',
  'content',
  'integrations',
  'kingdom',
  'transfers',
  'platform',
  'public',
];

const server = await createServer({
  root,
  configFile: false,
  appType: 'custom',
  logLevel: 'error',
  server: { middlewareMode: true },
});

async function load(modulePath) {
  return server.ssrLoadModule(`/resources/js/localization/messages/${modulePath}`);
}

function exportedKeys(module, exportName) {
  const catalogue = module[exportName];
  if (!catalogue?.en || typeof catalogue.en !== 'object') {
    throw new Error(`Expected ${exportName}.en to be an object.`);
  }

  return Object.keys(catalogue.en);
}

function unique(values) {
  return [...new Set(values)];
}

function pick(source, keys) {
  return Object.fromEntries(keys.filter((key) => key in source).map((key) => [key, source[key]]));
}

function isObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function deepDiff(value, baseline) {
  if (isObject(value) && isObject(baseline)) {
    const result = {};
    for (const [key, child] of Object.entries(value)) {
      const diff = deepDiff(child, baseline[key]);
      if (diff !== undefined) {
        result[key] = diff;
      }
    }
    return Object.keys(result).length > 0 ? result : undefined;
  }

  return Object.is(value, baseline) ? undefined : value;
}

function moduleSource(catalogue) {
  return `import type { MessageCatalogue } from '../../types';\n\nconst messages = ${JSON.stringify(catalogue, null, 2)} satisfies MessageCatalogue;\n\nexport default messages;\n`;
}

const finalMessages = (await load('index.ts')).messages;
const authExtra = await load('auth-extra.ts');
const account = await load('account-extra.ts');
const alliance = await load('alliance-operations.ts');
const eventCoordinator = await load('event-coordinator.ts');
const eventDetail = await load('event-detail.ts');
const roster = await load('roster.ts');
const rosterManagement = await load('roster-management.ts');
const rosterWorkflows = await load('roster-workflows.ts');
const recruitment = await load('recruitment.ts');
const content = await load('content-experience.ts');
const integrations = await load('integration-experience.ts');
const kingdomA = await load('kingdom-p7a.ts');
const kingdomB = await load('kingdom-p7b.ts');
const kingdomC = await load('kingdom-p7c.ts');
const transfers = await load('kingdom-p7d.ts');
const platform = await load('platform-p8.ts');
const publicMessages = await load('public.ts');
const publicExtra = await load('public-extra.ts');

const domainKeys = {
  auth: unique(['auth', ...exportedKeys(authExtra, 'authExtraMessages')]),
  account: exportedKeys(account, 'accountExperienceMessages'),
  alliance: exportedKeys(alliance, 'allianceOperationsMessages'),
  events: unique([
    ...exportedKeys(eventCoordinator, 'eventCoordinatorMessages'),
    ...exportedKeys(eventDetail, 'eventDetailMessages'),
  ]),
  roster: unique([
    ...exportedKeys(roster, 'rosterMessages'),
    ...exportedKeys(rosterManagement, 'rosterManagementMessages'),
    ...exportedKeys(rosterWorkflows, 'rosterWorkflowMessages'),
  ]),
  contributions: ['contributions'],
  recruitment: exportedKeys(recruitment, 'recruitmentMessages'),
  content: exportedKeys(content, 'contentMessages'),
  integrations: exportedKeys(integrations, 'integrationMessages'),
  kingdom: unique([
    ...exportedKeys(kingdomA, 'kingdomP7AMessages'),
    ...exportedKeys(kingdomB, 'kingdomP7BMessages'),
    ...exportedKeys(kingdomC, 'kingdomP7CMessages'),
  ]),
  transfers: exportedKeys(transfers, 'kingdomP7DMessages'),
  platform: exportedKeys(platform, 'platformP8Messages'),
  public: unique([
    ...exportedKeys(publicMessages, 'publicMessages'),
    ...exportedKeys(publicExtra, 'publicExtraMessages'),
  ]),
};

const ownership = new Map();
for (const [domain, keys] of Object.entries(domainKeys)) {
  for (const key of keys) {
    const owner = ownership.get(key);
    if (owner && owner !== domain) {
      throw new Error(`Top-level translation namespace ${key} belongs to both ${owner} and ${domain}.`);
    }
    ownership.set(key, domain);
  }
}

domainKeys.core = Object.keys(finalMessages.en).filter((key) => !ownership.has(key));

for (const domain of domains) {
  if (!domainKeys[domain] || domainKeys[domain].length === 0) {
    throw new Error(`Domain ${domain} has no top-level message namespaces.`);
  }
}

await rm(generatedRoot, { recursive: true, force: true });
await mkdir(generatedRoot, { recursive: true });

for (const domain of domains) {
  const domainDir = path.join(generatedRoot, domain);
  await mkdir(domainDir, { recursive: true });

  const english = pick(finalMessages.en, domainKeys[domain]);
  await writeFile(path.join(domainDir, 'en.ts'), moduleSource(english));

  for (const locale of localeCodes.filter((code) => code !== 'en')) {
    const localized = pick(finalMessages[locale], domainKeys[domain]);
    const overrides = deepDiff(localized, english) ?? {};
    await writeFile(path.join(domainDir, `${locale}.ts`), moduleSource(overrides));
  }
}

await server.close();
await rm(messagesRoot, { recursive: true, force: true });
await rename(generatedRoot, messagesRoot);

await writeFile(
  path.join(root, 'resources/js/localization/types.ts'),
  `export type TranslationParams = Record<string, string | number>;\n\nexport interface MessageCatalogue {\n  [key: string]: string | MessageCatalogue;\n}\n\nexport type MessageModule = { default: MessageCatalogue };\n`,
);

await writeFile(
  path.join(root, 'resources/js/localization/registry.ts'),
  `import type { LocaleCode } from './locales';\nimport type { MessageModule } from './types';\n\nexport const localizationDomains = [\n  'core',\n  'auth',\n  'account',\n  'alliance',\n  'events',\n  'roster',\n  'contributions',\n  'recruitment',\n  'content',\n  'integrations',\n  'kingdom',\n  'transfers',\n  'platform',\n  'public',\n] as const;\n\nexport type LocalizationDomain = (typeof localizationDomains)[number];\n\nconst catalogueModules = import.meta.glob<MessageModule>('./messages/*/*.ts');\n\nfunction cataloguePath(domain: LocalizationDomain, locale: LocaleCode): string {\n  return \`./messages/\${domain}/\${locale}.ts\`;\n}\n\nexport function hasDomainCatalogue(domain: LocalizationDomain, locale: LocaleCode): boolean {\n  return cataloguePath(domain, locale) in catalogueModules;\n}\n\nexport function hasLocaleCatalogue(locale: LocaleCode): boolean {\n  return hasDomainCatalogue('core', locale);\n}\n\nexport async function importDomainCatalogue(\n  domain: LocalizationDomain,\n  locale: LocaleCode,\n): Promise<MessageModule> {\n  const importer = catalogueModules[cataloguePath(domain, locale)];\n  if (!importer) {\n    throw new Error(\`Missing localization catalogue: \${domain}/\${locale}\`);\n  }\n  return importer();\n}\n\nexport function domainsForPage(name: string): LocalizationDomain[] {\n  const domains = new Set<LocalizationDomain>();\n\n  if (name.startsWith('Auth/')) {\n    domains.add('auth');\n  }\n\n  if (name === 'Profile' || name === 'AccountDeletion') {\n    domains.add('account');\n  }\n\n  if (name === 'Home' || name.startsWith('Public/')) {\n    domains.add('public');\n    if (name.includes('Recruitment')) domains.add('recruitment');\n    if (name.includes('Content')) domains.add('content');\n  }\n\n  if (name.startsWith('Platform/')) {\n    domains.add('platform');\n  }\n\n  if (name.startsWith('Alliance/')) {\n    domains.add('alliance');\n    const page = name.slice('Alliance/'.length);\n\n    if (page.startsWith('Events/')) domains.add('events');\n    if (page.startsWith('Contributions/')) domains.add('contributions');\n    if (page.startsWith('Integrations/')) domains.add('integrations');\n    if (page.startsWith('Recruitment/')) domains.add('recruitment');\n    if (page.startsWith('Content')) domains.add('content');\n    if (page.startsWith('Roster') || page.includes('Player')) domains.add('roster');\n    if (page.startsWith('Transfer')) domains.add('transfers');\n    else if (page.startsWith('Kingdom')) domains.add('kingdom');\n  }\n\n  return [...domains];\n}\n`,
);

await writeFile(
  path.join(root, 'resources/js/localization/loader.ts'),
  `import { defaultLocale, type LocaleCode } from './locales';\nimport { importDomainCatalogue, type LocalizationDomain } from './registry';\nimport type { MessageCatalogue } from './types';\n\nconst catalogues = new Map<string, MessageCatalogue>();\nconst pending = new Map<string, Promise<MessageCatalogue>>();\n\nfunction cacheKey(domain: LocalizationDomain, locale: LocaleCode): string {\n  return \`\${domain}:\${locale}\`;\n}\n\nfunction readPath(source: MessageCatalogue | undefined, path: string): string | null {\n  if (!source) return null;\n\n  const value = path.split('.').reduce<unknown>((node, segment) => {\n    if (!node || typeof node !== 'object') return null;\n    return (node as Record<string, unknown>)[segment] ?? null;\n  }, source);\n\n  return typeof value === 'string' ? value : null;\n}\n\nasync function loadOne(domain: LocalizationDomain, locale: LocaleCode): Promise<MessageCatalogue> {\n  const key = cacheKey(domain, locale);\n  const cached = catalogues.get(key);\n  if (cached) return cached;\n\n  const existing = pending.get(key);\n  if (existing) return existing;\n\n  const request = importDomainCatalogue(domain, locale).then((module) => {\n    catalogues.set(key, module.default);\n    pending.delete(key);\n    return module.default;\n  });\n  pending.set(key, request);\n  return request;\n}\n\nexport async function loadDomain(locale: LocaleCode, domain: LocalizationDomain): Promise<void> {\n  await loadOne(domain, defaultLocale);\n  if (locale !== defaultLocale) {\n    await loadOne(domain, locale);\n  }\n}\n\nexport async function loadDomains(\n  locale: LocaleCode,\n  domains: readonly LocalizationDomain[],\n): Promise<void> {\n  await Promise.all([...new Set(domains)].map((domain) => loadDomain(locale, domain)));\n}\n\nexport function isDomainLoaded(locale: LocaleCode, domain: LocalizationDomain): boolean {\n  return (\n    catalogues.has(cacheKey(domain, defaultLocale)) &&\n    (locale === defaultLocale || catalogues.has(cacheKey(domain, locale)))\n  );\n}\n\nexport function resolveMessage(\n  locale: LocaleCode,\n  domains: readonly LocalizationDomain[],\n  path: string,\n): string | null {\n  const ordered = [...new Set(domains)];\n\n  if (locale !== defaultLocale) {\n    for (const domain of ordered) {\n      const localized = readPath(catalogues.get(cacheKey(domain, locale)), path);\n      if (localized !== null) return localized;\n    }\n  }\n\n  for (const domain of ordered) {\n    const fallback = readPath(catalogues.get(cacheKey(domain, defaultLocale)), path);\n    if (fallback !== null) return fallback;\n  }\n\n  return null;\n}\n`,
);

await writeFile(
  path.join(root, 'resources/js/localization/index.ts'),
  `import { computed, readonly, ref } from 'vue';\n\nimport { loadDomains, resolveMessage } from './loader';\nimport {\n  defaultLocale,\n  localeDefinition,\n  locales,\n  normalizeLocale,\n  type LocaleCode,\n} from './locales';\nimport { domainsForPage, hasLocaleCatalogue, type LocalizationDomain } from './registry';\nimport type { TranslationParams } from './types';\n\nconst storageKey = 'kingshot.locale';\nconst currentLocale = ref<LocaleCode>(defaultLocale);\nlet currentPageDomains: LocalizationDomain[] = [];\nlet localeRequest = 0;\n\nfunction interpolate(value: string, params?: TranslationParams): string {\n  if (!params) return value;\n\n  return value.replace(/\\{([A-Za-z0-9_]+)\\}/g, (match, key: string) => {\n    const replacement = params[key];\n    return replacement === undefined ? match : String(replacement);\n  });\n}\n\nfunction applyDocumentLocale(locale: LocaleCode): void {\n  if (typeof document === 'undefined') return;\n  const definition = localeDefinition(locale);\n  document.documentElement.lang = definition.code;\n  document.documentElement.dir = definition.direction;\n}\n\nfunction activeDomains(): LocalizationDomain[] {\n  return ['core', ...currentPageDomains];\n}\n\nexport async function ensureDomain(domain: LocalizationDomain): Promise<void> {\n  await loadDomains(currentLocale.value, ['core', domain]);\n}\n\nexport async function ensurePageDomains(pageName: string): Promise<void> {\n  const domains = domainsForPage(pageName);\n  await loadDomains(currentLocale.value, ['core', ...domains]);\n  currentPageDomains = domains;\n}\n\nexport async function setLocale(locale: LocaleCode, persist = true): Promise<void> {\n  if (!hasLocaleCatalogue(locale)) return;\n\n  const request = ++localeRequest;\n  await loadDomains(locale, activeDomains());\n  if (request !== localeRequest) return;\n\n  currentLocale.value = locale;\n  applyDocumentLocale(locale);\n\n  if (persist && typeof window !== 'undefined') {\n    window.localStorage.setItem(storageKey, locale);\n  }\n}\n\nexport async function initializeLocale(preferredLocale?: string | null): Promise<void> {\n  const candidates: Array<string | null | undefined> = [preferredLocale];\n\n  if (typeof window !== 'undefined') {\n    candidates.push(window.localStorage.getItem(storageKey));\n    candidates.push(...window.navigator.languages);\n    candidates.push(window.navigator.language);\n  }\n\n  const locale = candidates\n    .map((candidate) => normalizeLocale(candidate))\n    .find(\n      (candidate): candidate is LocaleCode => candidate !== null && hasLocaleCatalogue(candidate),\n    );\n  const selected = locale ?? defaultLocale;\n\n  applyDocumentLocale(selected);\n  await loadDomains(selected, ['core']);\n  currentLocale.value = selected;\n}\n\nexport function t(key: string, params?: TranslationParams): string {\n  return interpolate(resolveMessage(currentLocale.value, activeDomains(), key) ?? key, params);\n}\n\nexport function formatNumber(value: number, options?: Intl.NumberFormatOptions): string {\n  return new Intl.NumberFormat(currentLocale.value, options).format(value);\n}\n\nexport function formatDate(\n  value: Date | string | number,\n  options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },\n): string {\n  return new Intl.DateTimeFormat(currentLocale.value, options).format(new Date(value));\n}\n\nexport function formatRelativeTime(value: number, unit: Intl.RelativeTimeFormatUnit): string {\n  return new Intl.RelativeTimeFormat(currentLocale.value, { numeric: 'auto' }).format(value, unit);\n}\n\nexport function useLocale() {\n  const definition = computed(() => localeDefinition(currentLocale.value));\n  const availableLocales = computed(() => locales.filter((locale) => hasLocaleCatalogue(locale.code)));\n\n  return {\n    locale: readonly(currentLocale),\n    definition,\n    direction: computed(() => definition.value.direction),\n    availableLocales,\n    setLocale,\n    ensureDomain,\n    formatNumber,\n    formatDate,\n    formatRelativeTime,\n    t,\n  };\n}\n\nexport { hasLocaleCatalogue } from './registry';\nexport type { LocalizationDomain } from './registry';\n`,
);

await writeFile(
  path.join(root, 'resources/js/app.ts'),
  `import '../css/app.css';\n\nimport { createInertiaApp } from '@inertiajs/vue3';\nimport { createApp, h, type DefineComponent } from 'vue';\n\nimport { ensurePageDomains, initializeLocale } from './localization';\n\nconst appName = import.meta.env.VITE_APP_NAME ?? 'Kingshot Alliance';\nconst pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { import: 'default' });\n\nasync function bootstrap(): Promise<void> {\n  await initializeLocale();\n\n  await createInertiaApp({\n    title: (title) => (title ? \`\${title} · \${appName}\` : appName),\n    resolve: async (name) => {\n      const page = pages[\`./pages/\${name}.vue\`];\n      if (!page) throw new Error(\`Page not found: \${name}\`);\n\n      await ensurePageDomains(name);\n      return page();\n    },\n    setup({ el, App, props, plugin }) {\n      createApp({ render: () => h(App, props) })\n        .use(plugin)\n        .mount(el);\n    },\n    progress: { color: '#e2b44d' },\n  });\n}\n\nvoid bootstrap();\n`,
);

const switcherPath = path.join(root, 'resources/js/components/navigation/LocaleSwitcher.vue');
let switcher = await readFile(switcherPath, 'utf8');
switcher = switcher.replace('function changeLocale(event: Event): void {', 'async function changeLocale(event: Event): Promise<void> {');
switcher = switcher.replace('    setLocale(nextLocale as LocaleCode);', '    await setLocale(nextLocale as LocaleCode);');
await writeFile(switcherPath, switcher);

const adminPath = path.join(root, 'resources/js/pages/Platform/Administration/Index.vue');
let admin = await readFile(adminPath, 'utf8');
admin = admin.replace("import { useLocale } from '../../../localization';", "import { hasLocaleCatalogue, useLocale } from '../../../localization';");
admin = admin.replace("import { hasMessageCatalogue } from '../../../localization/messages';\n", '');
admin = admin.replaceAll('hasMessageCatalogue(', 'hasLocaleCatalogue(');
await writeFile(adminPath, admin);

await writeFile(
  path.join(root, 'scripts/check-localization-chunks.mjs'),
  `import { readFile, stat } from 'node:fs/promises';\nimport path from 'node:path';\n\nconst manifestPath = path.resolve('public/build/manifest.json');\nconst manifest = JSON.parse(await readFile(manifestPath, 'utf8'));\nconst entries = Object.values(manifest);\nconst sources = new Set(entries.map((entry) => entry.src).filter(Boolean));\n\nconst pageSources = [...sources].filter((source) => source.startsWith('resources/js/pages/'));\nconst localeSources = [...sources].filter((source) =>\n  source.startsWith('resources/js/localization/messages/'),\n);\n\nif (pageSources.length < 10) {\n  throw new Error(\`Expected lazy Inertia page chunks; found only \${pageSources.length}.\`);\n}\n\nif (localeSources.length !== 14 * 17) {\n  throw new Error(\`Expected 238 domain/locale chunks; found \${localeSources.length}.\`);\n}\n\nconst appEntry = entries.find((entry) => entry.src === 'resources/js/app.ts');\nif (!appEntry?.file) throw new Error('Vite manifest is missing the app entry.');\nif (!Array.isArray(appEntry.dynamicImports) || appEntry.dynamicImports.length === 0) {\n  throw new Error('The app entry has no dynamic imports; page/domain splitting is not active.');\n}\n\nconst appStats = await stat(path.resolve('public/build', appEntry.file));\nconsole.log(\`Localization chunks: \${localeSources.length}; page chunks: \${pageSources.length}; app entry: \${Math.round(appStats.size / 1024)} KiB.\`);\n`,
);

const packagePath = path.join(root, 'package.json');
const packageJson = JSON.parse(await readFile(packagePath, 'utf8'));
packageJson.scripts['check:localization-chunks'] = 'node scripts/check-localization-chunks.mjs';
packageJson.scripts.check =
  'npm run lint:check && npm run format:check && npm run types:check && npm run build && npm run check:localization-chunks';
await writeFile(packagePath, `${JSON.stringify(packageJson, null, 2)}\n`);

const pathMap = new Map([
  ['resources/js/localization/messages/account-extra.ts', 'resources/js/localization/messages/account/en.ts'],
  ['resources/js/localization/messages/alliance-operations.ts', 'resources/js/localization/messages/alliance/en.ts'],
  ['resources/js/localization/messages/app-extra.ts', 'resources/js/localization/messages/core/en.ts'],
  ['resources/js/localization/messages/auth-extra.ts', 'resources/js/localization/messages/auth/en.ts'],
  ['resources/js/localization/messages/catalogues.ts', 'resources/js/localization/messages/core/en.ts'],
  ['resources/js/localization/messages/content-experience.ts', 'resources/js/localization/messages/content/en.ts'],
  ['resources/js/localization/messages/event-coordinator.ts', 'resources/js/localization/messages/events/en.ts'],
  ['resources/js/localization/messages/event-detail.ts', 'resources/js/localization/messages/events/en.ts'],
  ['resources/js/localization/messages/integration-experience.ts', 'resources/js/localization/messages/integrations/en.ts'],
  ['resources/js/localization/messages/kingdom-p7a.ts', 'resources/js/localization/messages/kingdom/en.ts'],
  ['resources/js/localization/messages/kingdom-p7b.ts', 'resources/js/localization/messages/kingdom/en.ts'],
  ['resources/js/localization/messages/kingdom-p7c.ts', 'resources/js/localization/messages/kingdom/en.ts'],
  ['resources/js/localization/messages/kingdom-p7d.ts', 'resources/js/localization/messages/transfers/en.ts'],
  ['resources/js/localization/messages/platform-p8.ts', 'resources/js/localization/messages/platform/en.ts'],
  ['resources/js/localization/messages/public-extra.ts', 'resources/js/localization/messages/public/en.ts'],
  ['resources/js/localization/messages/public.ts', 'resources/js/localization/messages/public/en.ts'],
  ['resources/js/localization/messages/recruitment.ts', 'resources/js/localization/messages/recruitment/en.ts'],
  ['resources/js/localization/messages/roster-management.ts', 'resources/js/localization/messages/roster/en.ts'],
  ['resources/js/localization/messages/roster-workflow-overrides.ts', 'resources/js/localization/messages/roster/en.ts'],
  ['resources/js/localization/messages/roster-workflows.ts', 'resources/js/localization/messages/roster/en.ts'],
  ['resources/js/localization/messages/roster.ts', 'resources/js/localization/messages/roster/en.ts'],
]);

for (const testRoot of ['tests/Architecture', 'tests/Feature']) {
  const stack = [path.join(root, testRoot)];
  while (stack.length > 0) {
    const directory = stack.pop();
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const fullPath = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        stack.push(fullPath);
        continue;
      }
      if (!entry.name.endsWith('.php')) continue;
      let source = await readFile(fullPath, 'utf8');
      const original = source;
      for (const [from, to] of pathMap) source = source.replaceAll(from, to);
      if (source !== original) await writeFile(fullPath, source);
    }
  }
}

await writeFile(
  path.join(root, 'tests/Architecture/LocalizationCodeSplittingTest.php'),
  `<?php\n\nuse App\\Support\\Localization\\Locale;\n\nit('keeps pages and localization catalogues code split by domain and locale', function (): void {\n    $app = file_get_contents(resource_path('js/app.ts'));\n    expect($app)\n        ->toContain("import.meta.glob<DefineComponent>('./pages/**/*.vue'")\n        ->not->toContain('eager: true')\n        ->toContain('await ensurePageDomains(name)');\n\n    $registry = file_get_contents(resource_path('js/localization/registry.ts'));\n    expect($registry)\n        ->toContain("import.meta.glob<MessageModule>('./messages/*/*.ts')")\n        ->toContain("'kingdom'")\n        ->toContain("'transfers'")\n        ->toContain("'platform'");\n\n    $domains = ['core', 'auth', 'account', 'alliance', 'events', 'roster', 'contributions', 'recruitment', 'content', 'integrations', 'kingdom', 'transfers', 'platform', 'public'];\n    $locales = ['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];\n\n    foreach ($domains as $domain) {\n        foreach ($locales as $locale) {\n            expect(resource_path("js/localization/messages/{$domain}/{$locale}.ts"))->toBeFile();\n        }\n    }\n\n    $legacyFiles = glob(resource_path('js/localization/messages/*.ts')) ?: [];\n    expect($legacyFiles)->toBe([]);\n});\n\nit('loads english fallback plus locale overrides and caches domain requests', function (): void {\n    $loader = file_get_contents(resource_path('js/localization/loader.ts'));\n    expect($loader)\n        ->toContain('const catalogues = new Map')\n        ->toContain('const pending = new Map')\n        ->toContain('await loadOne(domain, defaultLocale)')\n        ->toContain('if (locale !== defaultLocale)')\n        ->toContain('resolveMessage');\n\n    $runtime = file_get_contents(resource_path('js/localization/index.ts'));\n    expect($runtime)\n        ->toContain('export async function setLocale')\n        ->toContain('export async function ensurePageDomains')\n        ->toContain("return ['core', ...currentPageDomains]")\n        ->toContain('await loadDomains(locale, activeDomains())');\n});\n`,
);

console.log(`Generated ${domains.length * localeCodes.length} domain/locale catalogue modules.`);
