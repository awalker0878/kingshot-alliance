import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Allianz-Integrationen',
    title: 'API-Zugangsdaten & Webhooks',
    activeCredentials: 'Aktive Zugangsdaten',
    activeWebhooks: 'Aktive Webhooks',
    recentDeliveries: 'Letzte Zustellungen',
    apiCredentials: 'API-Zugangsdaten',
    createCredential: 'Zugangsdaten erstellen',
    revoke: 'Widerrufen',
    webhookSubscriptions: 'Webhook-Abonnements',
    createWebhook: 'Webhook erstellen',
    deliveryLog: 'Letztes Zustellprotokoll',
    event: 'Ereignis',
    attempts: 'Versuche',
    lastError: 'Letzter Fehler',
  },
} satisfies MessageCatalogue;

export default messages;
