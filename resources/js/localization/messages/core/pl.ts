import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Język',
    signIn: 'Zaloguj się',
    signOut: 'Wyloguj się',
    createAccount: 'Utwórz konto',
    continue: 'Dalej',
    cancel: 'Anuluj',
    save: 'Zapisz',
    close: 'Zamknij',
    loading: 'Ładowanie',
    openNavigation: 'Otwórz nawigację',
    closeNavigation: 'Zamknij nawigację',
    playerAlliance: 'Sojusz aktywnego gracza',
    noPlayerAlliance: 'Aktywny gracz nie ma aktywnego członkostwa w sojuszu.',
    skipToContent: 'Przejdź do treści',
  },
  navigation: {
    home: 'Strona główna',
    dashboard: 'Panel',
    alliance: 'Sojusz',
    events: 'Wydarzenia',
    roster: 'Członkowie',
    recruitment: 'Rekrutacja',
    content: 'Treści',
    contributions: 'Wkład',
    kingdom: 'Królestwo',
    transfers: 'Transfery',
    integrations: 'Integracje',
    profile: 'Profil',
    settings: 'Ustawienia',
    allianceOperations: 'Operacje sojuszu',
    kingdomOperations: 'Operacje królestwa',
    account: 'Konto',
  },
  application: {
    dashboard: {
      title: 'Panel',
      eyebrow: 'Dowództwo sojuszu',
      welcome: 'Witaj, {name}',
      verificationPending: 'Oczekiwanie na weryfikację e-maila',
      playerContextTitle: 'Aktywny gracz',
      playerContextIntro:
        'Zmiana gracza zmienia tożsamość w grze używaną do uprawnień sojuszu i królestwa.',
      playerKingdom: 'Królestwo #{kingdom}',
      playerAuthorityIntro:
        'Członkostwo, ranga, role, uprawnienia królestwa i akcje w grze są ustalane wyłącznie na podstawie tego gracza.',
      selectPlayer: 'Wybierz gracza, aby korzystać z narzędzi gry.',
      playerAllianceTitle: 'Sojusz aktywnego gracza',
      playerAllianceIntro:
        'Narzędzia sojuszu używają wyłącznie członkostwa, rangi i ról aktywnego gracza.',
      noPlayerAllianceTitle: 'Ten gracz nie należy do sojuszu',
      noPlayerAllianceIntro:
        'Zmień gracza albo utwórz/dołącz do sojuszu aktywnym graczem przed otwarciem narzędzi sojuszu.',
      openPlayerAlliance: 'Otwórz sojusz gracza',
      active: 'Aktywny',
      roles: 'Role',
      roster: 'Skład',
      kingdomAlliances: 'Sojusze królestwa',
      transfers: 'Transfery',
      kingdomSettings: 'Ustawienia królestwa',
      createTitle: 'Utwórz sojusz',
      createIntro:
        'Utwórz sojusz dla aktywnego gracza. Królestwo sojuszu wynika z tego gracza, który zostaje pierwszym R5.',
      allianceName: 'Nazwa sojuszu',
      timezone: 'Strefa czasowa',
      create: 'Utwórz sojusz',
    },
  },
} satisfies MessageCatalogue;

export default messages;
