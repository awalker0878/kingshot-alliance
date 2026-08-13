import type { LocaleCode } from '../locales';
import { additionalCatalogues } from './catalogues';
import en from './en';
import { publicExtraMessages } from './public-extra';
import { publicMessages } from './public';

type StringLeaves<T> = {
  [K in keyof T]: T[K] extends string ? string : T[K] extends object ? StringLeaves<T[K]> : never;
};

type BaseMessageTree = StringLeaves<typeof en>;
type PublicMessageTree = (typeof publicMessages)['en'];
type PublicExtraMessageTree = (typeof publicExtraMessages)['en'];

export type MessageTree = BaseMessageTree & PublicMessageTree & PublicExtraMessageTree;

const baseMessages: Record<LocaleCode, BaseMessageTree> = {
  en,
  ...additionalCatalogues,
};

export const messages = Object.fromEntries(
  (Object.keys(baseMessages) as LocaleCode[]).map((locale) => [
    locale,
    {
      ...baseMessages[locale],
      ...publicMessages[locale],
      ...publicExtraMessages[locale],
    },
  ]),
) as Record<LocaleCode, MessageTree>;

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return Object.prototype.hasOwnProperty.call(messages, locale);
}
