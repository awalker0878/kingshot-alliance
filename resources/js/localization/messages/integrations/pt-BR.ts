import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    recordCount: '{count} registros',
    historyUnavailable: 'Não foi possível carregar esta página.',
    retryPage: 'Tentar a página novamente',
    expired: 'Expirado',
    deliveryQueued: 'Na fila',
    deliveryHistory: 'Histórico de entregas',

    eyebrow: 'Integrações da aliança',
    title: 'Credenciais de API e webhooks',
    activeCredentials: 'Credenciais ativas',
    activeWebhooks: 'Webhooks ativos',
    recentDeliveries: 'Entregas recentes',
    apiCredentials: 'Credenciais de API',
    createCredential: 'Criar credencial',
    revoke: 'Revogar',
    webhookSubscriptions: 'Assinaturas de webhook',
    createWebhook: 'Criar webhook',
    deliveryLog: 'Registro de entregas recentes',
    event: 'Evento',
    attempts: 'Tentativas',
    lastError: 'Último erro',
  },
} satisfies MessageCatalogue;

export default messages;
