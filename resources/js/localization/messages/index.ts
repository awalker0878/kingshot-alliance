import type { LocaleCode } from '../locales';
import en from './en';

export type MessageTree = typeof en;

export const messages: Partial<Record<LocaleCode, MessageTree>> = {
  en,
};

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return locale in messages;
}
