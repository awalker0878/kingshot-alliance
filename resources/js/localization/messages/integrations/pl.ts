import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
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
