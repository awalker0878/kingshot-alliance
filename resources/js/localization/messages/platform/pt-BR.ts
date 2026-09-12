import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} registros',
    historyUnavailable: 'Não foi possível carregar esta página.',
    retryPage: 'Tentar a página novamente',
    title: 'Administração da plataforma',
    backDashboard: 'Voltar ao Início',
    capacityTitle: 'Capacidade e operações',
    administrators: 'Administradores da plataforma',
    provisionAlliance: 'Criar Aliança',
    allianceFleet: 'Alianças',
    legalHolds: 'Proteção de registros',
    localizationRuntime: 'Idiomas',
  },
} satisfies MessageCatalogue;

export default messages;
