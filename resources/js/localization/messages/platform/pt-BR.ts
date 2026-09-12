import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'A opção selecionada não está mais disponível.',
    searchChoices: 'Buscar opções',
    choicePageSummary:
      '{count} opções nesta página; {total} correspondem. Ordenadas por identificador estável.',
    choicesFailed:
      'Não foi possível carregar as opções. A seleção e a página exibida foram preservadas.',
    retryChoices: 'Tentar novamente',
    recoveryFailed: 'Não foi possível concluir a recuperação. Tente novamente.',
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
