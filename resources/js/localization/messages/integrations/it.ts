import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Integrazioni alleanza',
    title: 'Credenziali API e webhook',
    activeCredentials: 'Credenziali attive',
    activeWebhooks: 'Webhook attivi',
    recentDeliveries: 'Consegne recenti',
    apiCredentials: 'Credenziali API',
    createCredential: 'Crea credenziale',
    revoke: 'Revoca',
    webhookSubscriptions: 'Sottoscrizioni webhook',
    createWebhook: 'Crea webhook',
    deliveryLog: 'Registro consegne recenti',
    event: 'Evento',
    status: 'Stato',
    attempts: 'Tentativi',
    lastError: 'Ultimo errore',
  },
} satisfies MessageCatalogue;

export default messages;
