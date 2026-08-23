import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'Resumo da Caça ao Urso',
    eyebrow: 'Caça ao Urso · Pós-ação',
    subtitle:
      'Veja o dano registrado, presença, participação em Rally, Governadores não resolvidos e como esta Caça se compara às anteriores.',
    totalDamage: 'Dano total',
    governors: 'Governadores',
    governor: 'Governador',
    governorCount: '{count} Governadores',
    attendance: 'Presença',
    recordedRallies: 'Rallies registrados',
    notRecorded: 'Não registrado',
    notComparable: 'Comparação indisponível',
    noChange: 'Sem mudança em relação à Caça anterior',
    increased: 'aumentou',
    decreased: 'diminuiu',
    changeWithPercent: '{direction} em {amount} ({percent}%) vs. Caça anterior',
    change: '{direction} em {amount} vs. Caça anterior',
    rankUp: 'Subiu {count} posições vs. Caça anterior',
    rankDown: 'Caiu {count} posições vs. Caça anterior',
    yourHunt: 'Sua Caça',
    damage: 'Dano',
    rank: 'Classificação',
    alliancePerformance: 'Desempenho da Aliança',
    leaderboard: 'Ranking de Governadores',
    reportCount: '{count} relatórios de batalha registrados',
    unknownGovernor: 'Governador desconhecido',
    noResults: 'Ainda não há dano de Governadores registrado para esta Caça.',
    needsReview: 'Precisa de revisão',
    unmatchedGovernors: '{count} Governadores precisam ser vinculados',
    reviewHelp:
      'Conclua a identificação dos Governadores no Screenshot Intake. O resumo não cria um segundo fluxo de identidade.',
    reviewImport: 'Revisar relatório importado',
    trends: 'Tendências',
    runTrends: 'Tendências recentes da Caça ao Urso',
    yourDamageTrend: 'Seu dano por Caça',
    allianceDamageTrend: 'Dano da Aliança por Caça',
    previousHunt: 'Caça anterior',
    noPrevious: 'Sem Caça anterior',
    noPreviousHelp:
      'A comparação aparece quando existe uma Caça ao Urso concluída anteriormente para esta Aliança.',
    history: 'Histórico',
    runHistory: 'Histórico da Caça ao Urso',
  },
} satisfies MessageCatalogue;

export default messages;
