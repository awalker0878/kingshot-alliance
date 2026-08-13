import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Integrasi aliansi',
    title: 'Kredensial API & webhook',
    activeCredentials: 'Kredensial aktif',
    activeWebhooks: 'Webhook aktif',
    recentDeliveries: 'Pengiriman terbaru',
    apiCredentials: 'Kredensial API',
    createCredential: 'Buat kredensial',
    revoke: 'Cabut',
    webhookSubscriptions: 'Langganan webhook',
    createWebhook: 'Buat webhook',
    deliveryLog: 'Log pengiriman terbaru',
    event: 'Peristiwa',
    attempts: 'Percobaan',
    lastError: 'Error terakhir',
  },
} satisfies MessageCatalogue;

export default messages;
