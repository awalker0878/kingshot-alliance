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
  if (!importer) {
    throw new Error(`Missing localization catalogue: ${domain}/${locale}`);
  }
  return importer();
}

export function domainsForPage(name: string): LocalizationDomain[] {
  const domains = new Set<LocalizationDomain>();

  if (name.startsWith('Auth/')) {
    domains.add('auth');
  }

  if (name === 'Profile' || name === 'AccountDeletion') {
    domains.add('account');
  }

  if (name === 'Home' || name.startsWith('Public/')) {
    domains.add('public');
    if (name.includes('Recruitment')) domains.add('recruitment');
    if (name.includes('Content')) domains.add('content');
  }

  if (name.startsWith('Platform/')) {
    domains.add('platform');
    if (name === 'Platform/EventTypes') domains.add('events');
  }

  if (name.startsWith('Events/')) {
    domains.add('events');
  }

  if (name.startsWith('Alliance/')) {
    domains.add('alliance');
    const page = name.slice('Alliance/'.length);

    if (page.startsWith('Events/')) domains.add('events');
    if (page.startsWith('Contributions/')) domains.add('contributions');
    if (page.startsWith('Integrations/')) domains.add('integrations');
    if (page.startsWith('Recruitment/')) domains.add('recruitment');
    if (page.startsWith('Content')) domains.add('content');
    if (page.startsWith('Roster') || page.includes('Player')) domains.add('roster');
    if (page.startsWith('Transfer')) domains.add('transfers');
    else if (page.startsWith('Kingdom')) domains.add('kingdom');
  }

  return [...domains];
}
