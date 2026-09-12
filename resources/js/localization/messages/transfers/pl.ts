import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Szukaj opcji',
    choicePageSummary:
      '{count} opcji na tej stronie; {total} pasuje. Kolejność według stałego identyfikatora.',
    choiceUnavailable: 'Wybrana opcja nie jest już dostępna.',
    choicesFailed: 'Nie udało się wczytać opcji. Wybór i wyświetlana strona pozostają bez zmian.',
    retryChoices: 'Ponów wczytanie',

    participantPagination: 'Strony uczestników',
    participantPageSummary: '{count} uczestników na tej stronie; {total} w tym widoku.',
    participantPageOrder:
      'Kolejność według stałego identyfikatora rejestracji. Sumy obejmują cały widok.',
    participantPageUnavailable:
      'Nie udało się wczytać strony uczestników. Wyświetlana strona nie uległa zmianie.',
    retryParticipantPage: 'Ponów wczytywanie',
    participantFilterPageOnly:
      'Ten filtr kwalifikacji dotyczy bieżącej strony. Przejdź przez strony, aby sprawdzić wszystkich uczestników.',
    workflowHistoryUnavailable: 'Nie udało się wczytać historii procesu transferu.',
    workflowHistoryTotal: 'Liczba wpisów w tej historii: {count}.',
    observationHistoryUnavailable: 'Nie udało się wczytać historii obserwacji.',
    reloadHistory: 'Wczytaj historię ponownie',
    eyebrow: 'Transfer Królestwa',
    title: 'Planowanie transferu',
    readinessBoard: 'Gotowość',
    completion: 'Wynik',
    manageTransfers: 'Zarządzaj transferami',
    currentCycle: 'Bieżący cykl',
    participants: 'Uczestnicy',
    incoming: 'Przychodzący',
    outgoing: 'Odchodzący',
    staying: 'Zostaje',
    transferGroups: 'Grupy transferowe',
    player: 'Gubernator',
    gamePlayerId: 'ID gry Gubernatora',
    readinessTitle: 'Gotowość do transferu',
    completionTitle: 'Wynik transferu',
    recordCompletion: 'Zapisz wynik transferu',
    rosterHandoffRecorded: 'Skład sojuszu zaktualizowany',
    completedStatus: 'Zakończono',
    notCompletedStatus: 'Nie zakończono',
    readinessReady: 'Gotowy',
    readinessBlocked: 'Zablokowany',
    readinessConfirmed: 'Potwierdzony',
  },
} satisfies MessageCatalogue;

export default messages;
