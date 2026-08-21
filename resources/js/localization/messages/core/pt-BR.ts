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
    playerAlliance: 'Aliança do Governador ativo',
    noPlayerAlliance: 'O Governador ativo não está atualmente em uma Aliança.',
    skipToContent: 'Ir para o conteúdo',
  },
  navigation: {
    home: 'Início',
    dashboard: 'Visão geral da Aliança',
    alliance: 'Aliança',
    events: 'Eventos',
    roster: 'Membros da Aliança',
    recruitment: 'Recrutamento',
    content: 'Mural de avisos',
    contributions: 'Contribuições da Aliança',
    kingdom: 'Alianças do Reino',
    transfers: 'Transferência de Reino',
    integrations: 'Conexões',
    profile: 'Conta do Governador',
    settings: 'Configurações',
    allianceOperations: 'Aliança',
    kingdomOperations: 'Reino',
    account: 'Conta do Governador',
  },
  application: {
    dashboard: {
      title: 'Visão geral da Aliança',
      eyebrow: 'Sua Aliança',
      welcome: 'Bem-vindo, Governador {name}',
      verificationPending: 'Verificação de e-mail pendente',
      playerContextTitle: 'Governador ativo',
      playerContextIntro:
        'Troque de Governador para mudar a identidade do Kingshot usada nas ações da Aliança e do Reino.',
      playerKingdom: 'Reino #{kingdom}',
      playerAuthorityIntro:
        'O rank da Aliança, as funções, as responsabilidades do Reino e o acesso aos Eventos acompanham o Governador ativo.',
      selectPlayer: 'Selecionar Governador',
      playerAllianceTitle: 'Aliança do Governador ativo',
      playerAllianceIntro: 'O acesso à Aliança acompanha o rank e as funções do Governador ativo.',
      noPlayerAllianceTitle: 'Este Governador não está em uma Aliança',
      noPlayerAllianceIntro:
        'Troque de Governador, entre em uma Aliança ou crie uma Aliança para usar os recursos da Aliança.',
      openPlayerAlliance: 'Abrir Aliança',
      active: 'Ativa',
      roles: 'Funções da Aliança',
      roster: 'Membros da Aliança',
      kingdomAlliances: 'Alianças do Reino',
      transfers: 'Transferência de Reino',
      kingdomSettings: 'Configurações do Reino',
      createTitle: 'Criar uma Aliança',
      createIntro:
        'Crie uma Aliança para o Governador ativo. A Aliança usa o Reino desse Governador e o Governador fundador se torna R5.',
      allianceName: 'Nome da Aliança',
      timezone: 'Fuso horário da Aliança',
      create: 'Criar Aliança',
    },
  },
} satisfies MessageCatalogue;

export default messages;
