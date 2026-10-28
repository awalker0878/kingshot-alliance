import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Assistente',
    title: 'Pergunte à sua Aliança',
    eyebrow: 'Assistente da Aliança · Respostas autorizadas',
    subtitle:
      'Pergunte sobre Eventos, seu roster e RSVPs, suas atribuições, guias e observações da Aliança, planos de território publicados, dados do jogo com fontes e sua prontidão para transferência quando estiver no escopo.',
    authorizationHint: 'As respostas usam apenas dados que você tem autorização para ver.',
    tryAsking: 'Experimente perguntar',
    conversation: 'Conversa com o Assistente da Aliança',
    youAsked: 'Você perguntou',
    possibleEvents: 'Eventos possíveis',
    openEvent: 'Abrir Evento',
    sourcesHeading: 'Fontes usadas',
    sourceTime: 'Data da fonte: {time}',
    questionLabel: 'Pergunte à sua Aliança',
    questionPlaceholder: 'Que horas é Swordland e estou no roster?',
    inputHint: '{count}/{max} caracteres · Enter para perguntar · Shift+Enter para nova linha',
    asking: 'Consultando fontes…',
    ask: 'Perguntar',
    notRecorded: 'Não registrado',
    classifications: {
      operational_fact: 'Fato operacional',
      game_fact: 'Dados do jogo',
      alliance_strategy: 'Estratégia da Aliança',
      observation: 'Observação',
      alliance_command: 'Visão geral do oficial',
      event_readiness: 'prontidão do evento',
      bear_hunt_run: 'Bear Hunt corrida',
      roster_freshness: 'governador observação freshness',
      transfer_verification: 'Verificação de transferência',
      territory_comparison: 'Comparação de territórios',
    },
    sources: {
      event: 'Evento',
      roster: 'Lista de membros',
      alliance_content: 'Guia da Aliança',
      observation: 'Observação',
      game_fact: 'Dados do jogo',
      participation: 'Participação',
      battle_plan_assignment: 'Atribuição do plano de batalha',
      transfer_assessment: 'Avaliação de transferência',
      territory_plan_revision: 'Plano de território publicado',
      alliance_command: 'Visão geral do oficial',
      event_readiness: 'prontidão do evento',
      bear_hunt_run: 'Bear Hunt corrida',
      roster_freshness: 'governador observação freshness',
      transfer_verification: 'Verificação de transferência',
      territory_comparison: 'Comparação de territórios',
      alliance_settings: 'Configurações da aliança',
      alliance_governance_history: 'História da governança da aliança',
      alliance_roster_reconciliation: 'Reconciliação da lista de alianças',
    },
    prompts: {
      swordland: 'Que horas é Swordland e estou no roster?',
      nextEvent: 'Qual é o meu próximo Evento?',
      bearGuide: 'O que diz nosso guia de Bear Hunt?',
      observation: 'O que observamos sobre nosso adversário?',
      heroFact: 'De qual geração é Amadeus?',
      rsvpWeek: 'Para quais Eventos confirmei presença esta semana?',
      battleAssignment: 'Qual é minha atribuição em Swordland?',
      transferStatus: 'O que falta para minha transferência?',
      territoryPlan: 'Qual layout da colmeia estamos usando no Bear Hunt?',
      allianceCommand: 'O que precisa de atenção do oficial?',
      eventReadiness: 'O nosso próximo evento verificado está pronto?',
      rallyGaps: 'Quais lacunas de Rally permanecem para o próximo evento?',
      bearHuntHistory: 'Mostre nosso histórico de desempenho recente da Bear Hunt.',
      progressionFreshness: 'Quais observações do governador estão desatualizadas ou ausentes?',
      transferVerification: 'Quais participantes de Transferência precisam de verificação?',
      intelligenceChanges: 'O que a Inteligência Material mudou recentemente?',
      territoryComparison: 'Como o Território observado se compara com o plano publicado?',
    },
    answers: {
      transferVerificationIncomplete:
        'Este resumo avaliou {assessed} de {total} participantes da transferência. Pelo menos {count} precisam de atenção; {unassessed} não foram avaliados aqui. Abra a preparação da transferência para as verificações atuais.',
      help: 'Posso responder a partir de Eventos autorizados, seu roster e RSVPs, suas atribuições, guias e observações da Aliança, planos de território publicados, dados do jogo com fontes e seu status de transferência no escopo. Não uso conhecimento de KingShot sem fonte.',
      unsupported:
        'Só posso responder a partir das fontes autorizadas compatíveis. Não uso conhecimento geral de KingShot nem faço alterações pelo Assistente.',
      unavailable:
        'O Assistente da Aliança não consegue consultar as fontes agora. Tente novamente.',
      rateLimited: 'Você está perguntando rápido demais. Tente novamente em instantes.',
      validationError: 'Digite uma pergunta entre 2 e {max} caracteres.',
      noUpcomingEvent: 'Não encontrei um próximo Evento que você tenha autorização para ver.',
      eventSubjectMissing: 'Informe o Evento que você quer consultar.',
      eventNotFound: 'Não encontrei um próximo Evento autorizado correspondente a “{subject}”.',
      eventAmbiguous:
        'Encontrei mais de um Evento correspondente a “{subject}”. Abra abaixo o Evento desejado.',
      eventTime: '{event} começa em {startsAt}.',
      eventTimeNotRostered: '{event} começa em {startsAt}. Você não está atualmente no roster.',
      notRostered: 'Você não está atualmente no roster de {event}.',
      eventTimeRostered:
        '{event} começa em {startsAt}. Você está em {roster}. Função: {role}; posição: {slot}; status: {status}.',
      rostered:
        'Você está no roster de {event}, em {roster}. Função: {role}; posição: {slot}; status: {status}.',
      contentSubjectMissing:
        'Informe o Evento ou assunto cujo guia da Aliança você quer consultar.',
      contentNotFound: 'Não encontrei conteúdo publicado da Aliança correspondente a “{subject}”.',
      contentFound: 'Estratégia da Aliança — {title}: {excerpt}',
      observationSubjectMissing:
        'Informe a Aliança ou o assunto da observação que você quer consultar.',
      observationNotFound: 'Não encontrei uma observação autorizada correspondente a “{subject}”.',
      observationFound: 'Observação — {title}: {observation}',
      gameFactKnown:
        'Dados do jogo — {title}. Os valores com fonte abaixo vêm do conjunto {datasetVersion}.',
      gameFactUnknown:
        'Dados do jogo — não tenho um valor conhecido e compatível para {title}. Não vou adivinhar.',
      gameFactConflicting:
        'Dados do jogo — as evidências mantidas para {title} estão em conflito. Não vou escolher um valor por você.',
      participationNone: 'Você não tem RSVP ou inscrição registrada para {event}.',
      participationFound: 'Sua participação em {event} está abaixo.',
      participationWeekNone: 'Não encontrei RSVP ou inscrição sua nesta semana.',
      participationNotFound:
        'Não encontrei um estado correspondente de RSVP, inscrição ou lista de espera para você.',
      participationWeek: 'Encontrei {count} registro(s) de participação seu(s) nesta semana.',
      participationList: 'Encontrei {count} registro(s) de participação correspondente(s).',
      battlePlanNotFound:
        'Não encontrei um próximo Evento autorizado com uma atribuição para você.',
      battlePlanNone: 'Você não tem uma atribuição de plano de batalha para {event} no momento.',
      battlePlanOne: 'Você tem uma atribuição de plano de batalha para {event}.',
      battlePlanMany: 'Você tem {count} atribuições de plano de batalha para {event}.',
      transferNotInScope:
        'Não posso fornecer prontidão de transferência a partir do seu escopo autorizado atual.',
      transferStatus:
        'Sua avaliação de transferência é {outcome}. Os requisitos calculados pelo recurso proprietário estão abaixo.',
      territoryPlanNotFound: 'Não há revisão publicada de plano de território anexada a {event}.',
      territoryPlanAmbiguous:
        'Há várias revisões publicadas de plano de território anexadas a {event}; não vou escolher uma silenciosamente.',
      territoryPlanFound:
        '{event} está usando {planName}, revisão {revisionNumber}, para {purpose}.',
      rosterWriteHandoff:
        'Não posso alterar o roster daqui. Abra o fluxo normal de {event} para fazer a alteração.',
      ownerEventWriteHandoff:
        'Não consigo fazer essa alteração no Assistente. Abra o fluxo de trabalho normal do proprietário {event} para continuar.',
      ownerWriteHandoff:
        'Não consigo alterar o estado {owner} a partir do Assistente. Os fatos autorizados atuais são mostrados abaixo; abra o fluxo de trabalho do proprietário para continuar.',
      allianceCommandAttention:
        'A visão geral do oficial tem {count} item(ns) factuais que precisam de atenção. As fontes autorizadas são mostradas abaixo.',
      allianceCommandAttentionNotAvailable:
        'Não há fatos de visão geral de oficial autorizado disponíveis para esta aliança.',
      eventReadiness:
        'O próximo evento verificado tem {count} item(s) de prontidão de bloqueio. Sua projeção proprietária é mostrada abaixo.',
      eventReadinessNotAvailable:
        'Não consegui encontrar a disponibilidade verificada para eventos correspondente na sua visão geral de funcionários autorizados.',
      rallyGaps:
        'O próximo evento verificado tem {count} lacuna(s) de prontidão de Rally. Os fatos de propriedade do Rally são mostrados abaixo.',
      rallyGapsNotAvailable:
        'Não consegui encontrar fatos de prontidão de Rally para um evento verificado correspondente.',
      bearHuntHistory:
        'Eu encontrei {count} recentes autorizados Bear Hunt run record(s). Os estados de evidência registrados e aceitos permanecem explícitos abaixo.',
      bearHuntHistoryNotAvailable:
        'Não consegui encontrar uma corrida de Bear Hunt completa autorizada com histórico suportado.',
      progressionFreshness:
        '{count} observação(ões) do governador estão desatualizadas ou ausentes na projeção autorizada de atualização da lista.',
      progressionFreshnessNotAvailable:
        'A frequência de observação do governador não está disponível na sua visão geral do oficial autorizado.',
      transferVerification:
        '{count} Os participantes da transferência precisam de verificação ou estão bloqueados na avaliação do proprietário.',
      transferVerificationNotAvailable:
        'Não há nenhuma projeção de verificação de transferência autorizada atual.',
      territoryComparison:
        'A comparação autorizada do Território contém {count} diferença(s) observada(s). Observações ausentes e incompatíveis permanecem explícitas abaixo.',
      territoryComparisonNotAvailable:
        'Não há comparação de Território publicada autorizada disponível.',
      allianceSettings:
        'As configurações atuais da aliança são {name}, nome da URL {slug}, idioma {language} e fuso horário {timezone}.',
      allianceGovernanceHistory:
        'Encontrei {count} evento(s) de governança de aliança autorizado(s) recente(s). Estes são fatos de auditoria de proprietário somente leitura.',
      allianceRosterReconciliation:
        'A última observação de lista aceita tem {needsReview} item(ns) que precisam de revisão e {matched} correspondendo à associação atual.',
      allianceRosterReconciliationNotAvailable:
        'Não há nenhuma observação de lista de aliança aceita disponível para reconciliar ainda.',
    },
    possibleMatches: 'Possíveis correspondências',
    detailsHeading: 'Detalhes da resposta',
    requirementsHeading: 'Requisitos de transferência',
    datasetVersion: 'Versão do conjunto de dados',
    evidenceStatus: 'Status da evidência',
    checksum: 'Soma de verificação',
    revisionLabel: 'Revisão {revision}',
    participationSummary: 'RSVP: {response} · Inscrição: {registration}',
    waitlistPosition: 'Posição na lista de espera: {position}',
    teamLabel: 'Equipe: {team}',
    handoffs: {
      openRoster: 'Abrir roster do Evento',
      openOwnerWorkflow: 'Fluxo de trabalho de proprietário aberto',
    },
    transferRequirements: {
      window_phase: 'Fase da janela de transferência',
      transfer_group: 'Grupo de transferência',
      power_cap: 'Limite de poder',
      invitation: 'Convite',
      transfer_passes: 'Passes de transferência',
      in_game_rules: 'Regras no jogo',
    },
    transferStates: {
      met: 'Atendido',
      unmet: 'Não atendido',
      unknown: 'Desconhecido',
      stale: 'Desatualizado',
      conflicting: 'Conflitante',
      not_applicable: 'Não aplicável',
    },
    requirementValues: 'Atual: {actual} · Necessário: {required}',
  },
  intelligenceChange: {
    title: 'Mudanças recentes de inteligência',
    subtitle:
      'Mudanças com fonte derivadas do histórico autorizado. Descrevem observações, não intenção estratégica.',
    empty: 'Não há mudanças materiais de inteligência disponíveis para este escopo.',
    source: 'Fonte',
    observed: 'Observado',
    baseline: 'Referência',
    current: 'Atual',
    previous: 'Anterior',
    viewSource: 'Ver fonte',
    material: 'Mudança material',
    attention: 'Requer verificação',
  },
} satisfies MessageCatalogue;

export default messages;
