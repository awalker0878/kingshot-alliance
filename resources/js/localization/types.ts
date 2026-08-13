export type TranslationParams = Record<string, string | number>;

export interface MessageCatalogue {
  [key: string]: string | MessageCatalogue;
}

export type MessageModule = { default: MessageCatalogue };
