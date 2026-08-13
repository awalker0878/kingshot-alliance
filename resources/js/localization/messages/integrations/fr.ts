import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Intégrations de l’alliance',
    title: 'Identifiants API et webhooks',
    activeCredentials: 'Identifiants actifs',
    activeWebhooks: 'Webhooks actifs',
    recentDeliveries: 'Livraisons récentes',
    apiCredentials: 'Identifiants API',
    createCredential: 'Créer un identifiant',
    revoke: 'Révoquer',
    webhookSubscriptions: 'Abonnements webhook',
    createWebhook: 'Créer un webhook',
    deliveryLog: 'Journal des livraisons récentes',
    event: 'Événement',
    status: 'Statut',
    attempts: 'Tentatives',
    lastError: 'Dernière erreur',
  },
} satisfies MessageCatalogue;

export default messages;
