import type { MessageCatalogue } from '../../types';
const messages = {
  assistant: {
    navigation: 'Assistente',
    title: 'Pergunte à sua Aliança',
    eyebrow: 'Assistente da Aliança · Respostas autorizadas',
    subtitle:
      'Pergunte sobre Eventos, seu roster, guias da Aliança e observações. As respostas são baseadas em fontes que você já pode visualizar.',
    authorizationHint:
      'As respostas usam somente dados da Aliança que você tem autorização para ver.',
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
    },
    sources: {
      event: 'Evento',
      roster: 'Roster',
      alliance_content: 'Guia da Aliança',
      observation: 'Observação',
      game_fact: 'Dados do jogo',
    },
    prompts: {
      swordland: 'Que horas é Swordland e estou no roster?',
      nextEvent: 'Qual é o meu próximo Evento?',
      bearGuide: 'O que diz nosso guia de Bear Hunt?',
      observation: 'O que observamos sobre nosso adversário?',
    },
    answers: {
      help: 'Posso responder usando Eventos, seu roster, guias da Aliança e observações autorizadas. Não uso conhecimento de KingShot sem fonte.',
      unsupported:
        'Só posso responder usando Eventos autorizados, seu roster, guias da Aliança e observações. Não posso fazer alterações por aqui.',
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
    },
  },
} satisfies MessageCatalogue;
export default messages;
