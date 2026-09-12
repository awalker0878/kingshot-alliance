import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    recordCount: '{count} rekordów',
    historyUnavailable: 'Nie udało się wczytać tej strony.',
    retryPage: 'Ponów wczytanie strony',
    expired: 'Wygasłe',
    deliveryQueued: 'W kolejce',
    deliveryHistory: 'Historia dostarczania',

    eyebrow: 'Integracje sojuszu',
    title: 'Poświadczenia API i webhooki',
    activeCredentials: 'Aktywne poświadczenia',
    activeWebhooks: 'Aktywne webhooki',
    recentDeliveries: 'Ostatnie dostarczenia',
    apiCredentials: 'Poświadczenia API',
    createCredential: 'Utwórz poświadczenie',
    revoke: 'Unieważnij',
    webhookSubscriptions: 'Subskrypcje webhook',
    createWebhook: 'Utwórz webhook',
    deliveryLog: 'Dziennik ostatnich dostarczeń',
    event: 'Zdarzenie',
    attempts: 'Próby',
    lastError: 'Ostatni błąd',
  },
} satisfies MessageCatalogue;

export default messages;
