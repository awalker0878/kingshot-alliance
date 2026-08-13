import type { LocaleCode } from '../locales';
import { accountExperienceMessages } from './account-extra';
import { allianceOperationsMessages } from './alliance-operations';
import { applicationExtraMessages } from './app-extra';
import { authExtraMessages } from './auth-extra';
import { additionalCatalogues } from './catalogues';
import en from './en';
import { eventCoordinatorMessages } from './event-coordinator';
import { eventDetailMessages } from './event-detail';
import { publicExtraMessages } from './public-extra';
import { publicMessages } from './public';
import { rosterManagementMessages } from './roster-management';
import { rosterMessages } from './roster';
import { rosterWorkflowOverrides } from './roster-workflow-overrides';
import { rosterWorkflowMessages } from './roster-workflows';

type StringLeaves<T> = {
  [K in keyof T]: T[K] extends string ? string : T[K] extends object ? StringLeaves<T[K]> : never;
};

type BaseMessageTree = StringLeaves<typeof en>;
type AccountExperienceMessageTree = (typeof accountExperienceMessages)['en'];
type AllianceOperationsMessageTree = (typeof allianceOperationsMessages)['en'];
type ApplicationExtraMessageTree = (typeof applicationExtraMessages)['en'];
type AuthExtraMessageTree = (typeof authExtraMessages)['en'];
type EventCoordinatorMessageTree = (typeof eventCoordinatorMessages)['en'];
type EventDetailMessageTree = (typeof eventDetailMessages)['en'];
type PublicMessageTree = (typeof publicMessages)['en'];
type PublicExtraMessageTree = (typeof publicExtraMessages)['en'];
type RosterManagementMessageTree = (typeof rosterManagementMessages)['en'];
type RosterMessageTree = (typeof rosterMessages)['en'];
type RosterWorkflowMessageTree = (typeof rosterWorkflowMessages)['en'];

export type MessageTree = BaseMessageTree &
  AccountExperienceMessageTree &
  AllianceOperationsMessageTree &
  ApplicationExtraMessageTree &
  AuthExtraMessageTree &
  EventCoordinatorMessageTree &
  EventDetailMessageTree &
  PublicMessageTree &
  PublicExtraMessageTree &
  RosterManagementMessageTree &
  RosterMessageTree &
  RosterWorkflowMessageTree;

const baseMessages: Record<LocaleCode, BaseMessageTree> = {
  en,
  ...additionalCatalogues,
};

function catalogue(locale: LocaleCode): MessageTree {
  return {
    ...baseMessages[locale],
    ...accountExperienceMessages[locale],
    ...allianceOperationsMessages[locale],
    ...applicationExtraMessages[locale],
    ...authExtraMessages[locale],
    ...eventCoordinatorMessages[locale],
    ...eventDetailMessages[locale],
    ...publicMessages[locale],
    ...publicExtraMessages[locale],
    ...rosterManagementMessages[locale],
    rosterManage: {
      ...rosterManagementMessages[locale].rosterManage,
      trackedPlayers: rosterMessages[locale].roster.trackedPlayers,
    },
    ...rosterMessages[locale],
    ...rosterWorkflowMessages[locale],
    ...(rosterWorkflowOverrides[locale] ?? {}),
  };
}

export const messages: Record<LocaleCode, MessageTree> = Object.fromEntries(
  (Object.keys(baseMessages) as LocaleCode[]).map((locale) => [locale, catalogue(locale)]),
) as Record<LocaleCode, MessageTree>;

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return Object.prototype.hasOwnProperty.call(messages, locale);
}
