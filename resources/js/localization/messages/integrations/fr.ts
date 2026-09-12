import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    recordCount: '{count} enregistrements',
    historyUnavailable: 'Impossible de charger cette page.',
    retryPage: 'Réessayer la page',
    expired: 'Expiré',
    deliveryQueued: 'En attente',
    deliveryHistory: 'Historique des envois',

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
