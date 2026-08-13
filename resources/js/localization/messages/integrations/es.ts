import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Integraciones de la alianza',
    title: 'Credenciales API y webhooks',
    activeCredentials: 'Credenciales activas',
    activeWebhooks: 'Webhooks activos',
    recentDeliveries: 'Entregas recientes',
    apiCredentials: 'Credenciales API',
    createCredential: 'Crear credencial',
    revoke: 'Revocar',
    webhookSubscriptions: 'Suscripciones webhook',
    createWebhook: 'Crear webhook',
    deliveryLog: 'Registro de entregas recientes',
    event: 'Evento',
    status: 'Estado',
    attempts: 'Intentos',
    lastError: 'Último error',
  },
} satisfies MessageCatalogue;

export default messages;
