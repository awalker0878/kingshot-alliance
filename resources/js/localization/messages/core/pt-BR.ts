import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Idioma',
    signIn: 'Entrar',
    signOut: 'Sair',
    createAccount: 'Criar conta',
    continue: 'Continuar',
    cancel: 'Cancelar',
    save: 'Salvar',
    close: 'Fechar',
    loading: 'Carregando',
    openNavigation: 'Abrir navegação',
    closeNavigation: 'Fechar navegação',
    playerAlliance: 'Aliança do jogador ativo',
    noPlayerAlliance: 'O jogador ativo não possui uma associação ativa a uma aliança.',
    skipToContent: 'Ir para o conteúdo',
  },
  navigation: {
    home: 'Início',
    dashboard: 'Painel',
    alliance: 'Aliança',
    events: 'Eventos',
    roster: 'Membros',
    recruitment: 'Recrutamento',
    content: 'Conteúdo',
    contributions: 'Contribuições',
    kingdom: 'Reino',
    transfers: 'Transferências',
    integrations: 'Integrações',
    profile: 'Perfil',
    settings: 'Configurações',
    allianceOperations: 'Operações da aliança',
    kingdomOperations: 'Operações do reino',
    account: 'Conta',
  },
  application: {
    dashboard: {
      title: 'Painel',
      eyebrow: 'Comando da aliança',
      welcome: 'Bem-vindo, {name}',
      verificationPending: 'Verificação de e-mail pendente',
      playerContextTitle: 'Jogador ativo',
      playerContextIntro:
        'Trocar de jogador muda a identidade do jogo usada para autoridade de aliança e reino.',
      playerKingdom: 'Reino #{kingdom}',
      playerAuthorityIntro:
        'Associação, rank, funções, permissões do reino e ações do jogo são resolvidos somente a partir deste jogador.',
      selectPlayer: 'Selecionar governador',
      playerAllianceTitle: 'Aliança do jogador ativo',
      playerAllianceIntro:
        'As ferramentas da aliança usam somente a associação, o rank e as funções do jogador ativo.',
      noPlayerAllianceTitle: 'Este jogador não está em uma aliança',
      noPlayerAllianceIntro:
        'Troque de jogador ou crie/entre em uma aliança com o jogador ativo antes de abrir as ferramentas da aliança.',
      openPlayerAlliance: 'Abrir aliança do jogador',
      active: 'Ativa',
      roles: 'Funções',
      roster: 'Elenco',
      kingdomAlliances: 'Alianças do reino',
      transfers: 'Transferências',
      kingdomSettings: 'Configurações do reino',
      createTitle: 'Criar uma aliança',
      createIntro:
        'Crie uma aliança para o jogador ativo. O reino da aliança é derivado desse jogador, que se torna o R5 inicial.',
      allianceName: 'Nome da aliança',
      timezone: 'Fuso horário',
      create: 'Criar aliança',
    },
  },
} satisfies MessageCatalogue;

export default messages;
