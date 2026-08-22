import type { LocaleCode } from './locales';
import type { MessageModule } from './types';

export const localizationDomains = [
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
  'territory',
  'transfers',
  'platform',
  'public',
] as const;

export type LocalizationDomain = (typeof localizationDomains)[number];
const catalogueModules = import.meta.glob<MessageModule>('./messages/*/*.ts');
function cataloguePath(domain: LocalizationDomain, locale: LocaleCode): string {
  return `./messages/${domain}/${locale}.ts`;
}
export function hasDomainCatalogue(domain: LocalizationDomain, locale: LocaleCode): boolean {
  return cataloguePath(domain, locale) in catalogueModules;
}
export function hasLocaleCatalogue(locale: LocaleCode): boolean {
  return hasDomainCatalogue('core', locale);
}
export async function importDomainCatalogue(
  domain: LocalizationDomain,
  locale: LocaleCode,
): Promise<MessageModule> {
  const importer = catalogueModules[cataloguePath(domain, locale)];
  if (!importer) throw new Error(`Missing localization catalogue: ${domain}/${locale}`);
  return importer();
}
export function domainsForPage(name: string): LocalizationDomain[] {
  const domains = new Set<LocalizationDomain>();
  if (name.startsWith('Accounts/Access/')) domains.add('auth');
  if (name.startsWith('Accounts/Governor/')) domains.add('account');
  if (name.startsWith('Accounts/Notifications/')) domains.add('account');
  if (name.startsWith('Public/')) {
    domains.add('public');
    if (name.startsWith('Public/Recruitment/')) domains.add('recruitment');
    if (name.startsWith('Public/Alliance/')) domains.add('content');
  }
  if (name.startsWith('Dashboard/')) domains.add('alliance');
  if (name.startsWith('Alliance/')) {
    domains.add('alliance');
    if (name.startsWith('Alliance/Recruitment/')) domains.add('recruitment');
    if (name.startsWith('Alliance/Noticeboard/')) domains.add('content');
    if (name.startsWith('Alliance/Connections/')) domains.add('integrations');
    if (name.startsWith('Alliance/Members/')) domains.add('roster');
  }
  if (name.startsWith('Operations/Events/')) {
    domains.add('events');
    if (name === 'Operations/Events/Manage') domains.add('territory');
  }
  if (name.startsWith('Intelligence/')) {
    domains.add('kingdom');
    if (name.startsWith('Intelligence/Roster/')) domains.add('roster');
    if (name.startsWith('Intelligence/Contributions/')) domains.add('contributions');
  }
  if (name.startsWith('Kingdom/')) {
    domains.add('kingdom');
    if (name.startsWith('Kingdom/Territory/')) domains.add('territory');
    if (name.startsWith('Kingdom/Transfer/')) domains.add('transfers');
  }
  if (name.startsWith('Platform/')) {
    domains.add('platform');
    if (name.startsWith('Platform/EventTypes/')) domains.add('events');
  }
  return [...domains];
}
