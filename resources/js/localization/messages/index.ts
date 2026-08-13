import type { LocaleCode } from '../locales';
import { additionalCatalogues } from './catalogues';
import en from './en';

type StringLeaves<T> = {
  [K in keyof T]: T[K] extends string ? string : T[K] extends object ? StringLeaves<T[K]> : never;
};

export type MessageTree = StringLeaves<typeof en>;

export const messages: Record<LocaleCode, MessageTree> = {
  en,
  ...additionalCatalogues,
};

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return Object.prototype.hasOwnProperty.call(messages, locale);
}
