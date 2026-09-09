import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
