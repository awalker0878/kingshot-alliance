import type { LocaleCode } from '../locales';
import { applicationExtraMessages } from './app-extra';
import { authExtraMessages } from './auth-extra';
import { additionalCatalogues } from './catalogues';
import en from './en';
import { publicExtraMessages } from './public-extra';
import { publicMessages } from './public';

type StringLeaves<T> = {
  [K in keyof T]: T[K] extends string ? string : T[K] extends object ? StringLeaves<T[K]> : never;
};

type BaseMessageTree = StringLeaves<typeof en>;
type ApplicationExtraMessageTree = (typeof applicationExtraMessages)['en'];
type AuthExtraMessageTree = (typeof authExtraMessages)['en'];
type PublicMessageTree = (typeof publicMessages)['en'];
type PublicExtraMessageTree = (typeof publicExtraMessages)['en'];

export type MessageTree = BaseMessageTree &
  ApplicationExtraMessageTree &
  AuthExtraMessageTree &
  PublicMessageTree &
  PublicExtraMessageTree;

const baseMessages: Record<LocaleCode, BaseMessageTree> = {
  en,
  ...additionalCatalogues,
};

export const messages = Object.fromEntries(
  (Object.keys(baseMessages) as LocaleCode[]).map((locale) => [
    locale,
    {
      ...baseMessages[locale],
      ...applicationExtraMessages[locale],
      ...authExtraMessages[locale],
      ...publicMessages[locale],
      ...publicExtraMessages[locale],
    },
  ]),
) as Record<LocaleCode, MessageTree>;

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return Object.prototype.hasOwnProperty.call(messages, locale);
}
