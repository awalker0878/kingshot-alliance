import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'İttifak entegrasyonları',
    title: 'API kimlik bilgileri ve webhooklar',
    activeCredentials: 'Etkin kimlik bilgileri',
    activeWebhooks: 'Etkin webhooklar',
    recentDeliveries: 'Son teslimatlar',
    apiCredentials: 'API kimlik bilgileri',
    createCredential: 'Kimlik bilgisi oluştur',
    revoke: 'İptal et',
    webhookSubscriptions: 'Webhook abonelikleri',
    createWebhook: 'Webhook oluştur',
    deliveryLog: 'Son teslimat günlüğü',
    event: 'Olay',
    status: 'Durum',
    attempts: 'Denemeler',
    lastError: 'Son hata',
  },
} satisfies MessageCatalogue;

export default messages;
