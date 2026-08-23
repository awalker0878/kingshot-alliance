import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'Podsumowanie Polowania na Niedźwiedzia',
    eyebrow: 'Polowanie na Niedźwiedzia · Analiza po walce',
    subtitle:
      'Sprawdź zapisane obrażenia, obecność, udział w Rally, nierozpoznanych Gubernatorów i porównanie z ostatnimi polowaniami.',
    totalDamage: 'Łączne obrażenia',
    governors: 'Gubernatorzy',
    governor: 'Gubernator',
    governorCount: '{count} Gubernatorów',
    attendance: 'Obecność',
    recordedRallies: 'Zapisane Rally',
    notRecorded: 'Nie zapisano',
    notComparable: 'Brak porównania',
    noChange: 'Bez zmian względem poprzedniego polowania',
    increased: 'wzrost',
    decreased: 'spadek',
    changeWithPercent: '{direction} o {amount} ({percent}%) względem poprzedniego polowania',
    change: '{direction} o {amount} względem poprzedniego polowania',
    rankUp: 'Awans o {count} miejsc',
    rankDown: 'Spadek o {count} miejsc',
    yourHunt: 'Twoje polowanie',
    damage: 'Obrażenia',
    rank: 'Miejsce',
    alliancePerformance: 'Wynik Sojuszu',
    leaderboard: 'Ranking Gubernatorów',
    reportCount: '{count} zapisanych raportów bitewnych',
    unknownGovernor: 'Nieznany Gubernator',
    noResults: 'Dla tego polowania nie zapisano jeszcze obrażeń Gubernatorów.',
    needsReview: 'Wymaga przeglądu',
    unmatchedGovernors: '{count} Gubernatorów wymaga dopasowania',
    reviewHelp:
      'Dokończ dopasowanie Gubernatorów w Screenshot Intake. Podsumowanie nie tworzy osobnego procesu tożsamości.',
    reviewImport: 'Przejrzyj importowany raport',
    trends: 'Trendy',
    runTrends: 'Ostatnie trendy Polowania na Niedźwiedzia',
    yourDamageTrend: 'Twoje obrażenia według polowania',
    allianceDamageTrend: 'Obrażenia Sojuszu według polowania',
    previousHunt: 'Poprzednie polowanie',
    noPrevious: 'Brak poprzedniego polowania',
    noPreviousHelp:
      'Porównanie pojawi się, gdy dla tego Sojuszu istnieje wcześniejsze zakończone Polowanie na Niedźwiedzia.',
    history: 'Historia',
    runHistory: 'Historia Polowań na Niedźwiedzia',
  },
} satisfies MessageCatalogue;

export default messages;
