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
    playerAlliance: 'Sojusz aktywnego Gubernatora',
    noPlayerAlliance: 'Aktywny Gubernator nie należy obecnie do Sojuszu.',
    skipToContent: 'Przejdź do treści',
  },
  navigation: {
    home: 'Strona główna',
    dashboard: 'Przegląd Sojuszu',
    alliance: 'Sojusz',
    events: 'Wydarzenia',
    roster: 'Członkowie Sojuszu',
    recruitment: 'Rekrutacja',
    content: 'Tablica ogłoszeń',
    contributions: 'Wkład Sojuszu',
    kingdom: 'Sojusze Królestwa',
    transfers: 'Transfer Królestwa',
    integrations: 'Połączenia',
    profile: 'Konto Gubernatora',
    settings: 'Ustawienia',
    allianceOperations: 'Sojusz',
    kingdomOperations: 'Królestwo',
    account: 'Konto Gubernatora',
  },
  application: {
    dashboard: {
      title: 'Przegląd Sojuszu',
      eyebrow: 'Twój Sojusz',
      welcome: 'Witaj, Gubernatorze {name}',
      verificationPending: 'Oczekiwanie na weryfikację e-maila',
      playerContextTitle: 'Aktywny Gubernator',
      playerContextIntro:
        'Zmień Gubernatora, aby zmienić tożsamość Kingshot używaną do działań Sojuszu i Królestwa.',
      playerKingdom: 'Królestwo #{kingdom}',
      playerAuthorityIntro:
        'Ranga Sojuszu, role, obowiązki w Królestwie i dostęp do Wydarzeń podążają za aktywnym Gubernatorem.',
      selectPlayer: 'Wybierz Gubernatora',
      playerAllianceTitle: 'Sojusz aktywnego Gubernatora',
      playerAllianceIntro: 'Dostęp do Sojuszu zależy od rangi i ról aktywnego Gubernatora.',
      noPlayerAllianceTitle: 'Ten Gubernator nie należy do Sojuszu',
      noPlayerAllianceIntro:
        'Zmień Gubernatora, dołącz do Sojuszu lub utwórz Sojusz, aby korzystać z funkcji Sojuszu.',
      openPlayerAlliance: 'Otwórz Sojusz',
      active: 'Aktywny',
      roles: 'Role Sojuszu',
      roster: 'Członkowie Sojuszu',
      kingdomAlliances: 'Sojusze Królestwa',
      transfers: 'Transfer Królestwa',
      kingdomSettings: 'Ustawienia Królestwa',
      createTitle: 'Utwórz Sojusz',
      createIntro:
        'Utwórz Sojusz dla aktywnego Gubernatora. Sojusz używa Królestwa tego Gubernatora, a Gubernator założyciel zostaje R5.',
      allianceName: 'Nazwa Sojuszu',
      timezone: 'Strefa czasowa Sojuszu',
      create: 'Utwórz Sojusz',
    },
  },
} satisfies MessageCatalogue;

export default messages;
