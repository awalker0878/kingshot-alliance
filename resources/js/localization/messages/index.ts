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
import { rosterMessages } from './roster';

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
type RosterMessageTree = (typeof rosterMessages)['en'];

export type MessageTree = BaseMessageTree &
  AccountExperienceMessageTree &
  AllianceOperationsMessageTree &
  ApplicationExtraMessageTree &
  AuthExtraMessageTree &
  EventCoordinatorMessageTree &
  EventDetailMessageTree &
  PublicMessageTree &
  PublicExtraMessageTree &
  RosterMessageTree;

const baseMessages: Record<LocaleCode, BaseMessageTree> = {
  en,
  ...additionalCatalogues,
};

export const messages = Object.fromEntries(
  (Object.keys(baseMessages) as LocaleCode[]).map((locale) => [
    locale,
    {
      ...baseMessages[locale],
      ...accountExperienceMessages[locale],
      ...allianceOperationsMessages[locale],
      ...applicationExtraMessages[locale],
      ...authExtraMessages[locale],
      ...eventCoordinatorMessages[locale],
      ...eventDetailMessages[locale],
      ...publicMessages[locale],
      ...publicExtraMessages[locale],
      ...rosterMessages[locale],
    },
  ]),
) as Record<LocaleCode, MessageTree>;

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return Object.prototype.hasOwnProperty.call(messages, locale);
}
