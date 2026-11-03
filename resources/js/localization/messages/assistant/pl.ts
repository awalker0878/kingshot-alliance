import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Asystent',
    title: 'Zapytaj swój Sojusz',
    eyebrow: 'Asystent Sojuszu · Autoryzowane odpowiedzi',
    subtitle:
      'Pytaj o wydarzenia, własny roster i RSVP, własne przydziały, poradniki i obserwacje Sojuszu, opublikowane plany terytorium, dane gry ze źródłami oraz gotowość do transferu w dozwolonym zakresie.',
    authorizationHint: 'Odpowiedzi używają wyłącznie danych, które masz prawo zobaczyć.',
    tryAsking: 'Spróbuj zapytać',
    conversation: 'Rozmowa z Asystentem Sojuszu',
    youAsked: 'Twoje pytanie',
    possibleEvents: 'Możliwe Wydarzenia',
    openEvent: 'Otwórz Wydarzenie',
    sourcesHeading: 'Użyte źródła',
    sourceTime: 'Czas źródła: {time}',
    questionLabel: 'Zapytaj swój Sojusz',
    questionPlaceholder: 'O której jest Swordland i czy jestem w rosterze?',
    inputHint: '{count}/{max} znaków · Enter, aby zapytać · Shift+Enter, aby dodać wiersz',
    asking: 'Sprawdzanie źródeł…',
    ask: 'Zapytaj',
    notRecorded: 'Brak danych',
    classifications: {
      operational_fact: 'Fakt operacyjny',
      game_fact: 'Dane gry',
      alliance_strategy: 'Strategia Sojuszu',
      observation: 'Obserwacja',
      alliance_command: 'Przegląd funkcjonariuszy',
      event_readiness: 'Gotowość do zdarzenia',
      bear_hunt_run: 'Przebieg polowania na niedźwiedzia',
      roster_freshness: 'Świeżość obserwacji gubernatora',
      transfer_verification: 'Weryfikacja transferu',
      territory_comparison: 'Porównanie terytorialne',
    },
    sources: {
      event: 'Wydarzenie',
      roster: 'Lista członków',
      alliance_content: 'Poradnik Sojuszu',
      observation: 'Obserwacja',
      game_fact: 'Dane gry',
      participation: 'Udział',
      battle_plan_assignment: 'Przydział planu bitwy',
      transfer_assessment: 'Ocena transferu',
      territory_plan_revision: 'Opublikowany plan terytorium',
      alliance_command: 'Przegląd funkcjonariuszy',
      event_readiness: 'Gotowość do zdarzenia',
      bear_hunt_run: 'Przebieg polowania na niedźwiedzia',
      roster_freshness: 'Świeżość obserwacji gubernatora',
      transfer_verification: 'Weryfikacja transferu',
      territory_comparison: 'Porównanie terytorialne',
      alliance_settings: 'Ustawienia sojuszu',
      alliance_governance_history: 'Historia zarządzania sojuszem',
      alliance_roster_reconciliation: 'Pojednanie w rosterze sojuszu',
    },
    prompts: {
      swordland: 'O której jest Swordland i czy jestem w rosterze?',
      nextEvent: 'Jakie jest moje następne Wydarzenie?',
      bearGuide: 'Co mówi nasz poradnik Bear Hunt?',
      observation: 'Co zaobserwowaliśmy u przeciwnika?',
      heroFact: 'Z której generacji jest Amadeus?',
      rsvpWeek: 'Na co odpowiedziałem RSVP w tym tygodniu?',
      battleAssignment: 'Jaki jest mój przydział w Swordland?',
      transferStatus: 'Czego brakuje mi do transferu?',
      territoryPlan: 'Którego układu hive używamy na Bear Hunt?',
      allianceCommand: 'Co wymaga uwagi oficera?',
      eventReadiness: 'Czy nasze następne zweryfikowane wydarzenie jest gotowe?',
      rallyGaps: 'Jakie luki w Rajdzie pozostają do następnego wydarzenia?',
      bearHuntHistory: 'Pokaż naszą najnowszą historię wydajności Bear Hunt.',
      progressionFreshness: 'Które obserwacje gubernatora są przestarzałe lub brakuje?',
      transferVerification: 'Którzy uczestnicy Transferu potrzebują weryfikacji?',
      intelligenceChanges: 'Co się ostatnio zmieniło w Inteligencji?',
      territoryComparison:
        'Jak wygląda obserwowane Terytorium w porównaniu z opublikowanym planem?',
    },
    answers: {
      transferVerificationIncomplete:
        'Ten przegląd ocenił {assessed} z {total} uczestników transferu. Co najmniej {count} wymaga uwagi; {unassessed} nie oceniono tutaj. Otwórz gotowość do transferu, aby sprawdzić aktualne dane.',
      help: 'Mogę odpowiadać na podstawie autoryzowanych wydarzeń, własnego rosteru i RSVP, własnych przydziałów, poradników i obserwacji Sojuszu, opublikowanych planów terytorium, danych gry ze źródłami oraz statusu transferu w zakresie. Nie używam KingShot bez źródeł.',
      unsupported:
        'Mogę odpowiadać tylko z obsługiwanych autoryzowanych źródeł. Nie używam ogólnej wiedzy KingShot ani nie dokonuję zmian z Asystenta.',
      unavailable: 'Asystent Sojuszu nie może teraz sprawdzić źródeł. Spróbuj ponownie.',
      rateLimited: 'Pytasz zbyt szybko. Spróbuj ponownie za chwilę.',
      validationError: 'Wpisz pytanie od 2 do {max} znaków.',
      noUpcomingEvent: 'Nie znalazłem nadchodzącego Wydarzenia, które możesz wyświetlić.',
      eventSubjectMissing: 'Podaj Wydarzenie, które mam sprawdzić.',
      eventNotFound:
        'Nie znalazłem autoryzowanego nadchodzącego Wydarzenia pasującego do „{subject}”.',
      eventAmbiguous:
        'Znalazłem więcej niż jedno Wydarzenie pasujące do „{subject}”. Otwórz właściwe poniżej.',
      eventTime: '{event} rozpoczyna się {startsAt}.',
      eventTimeNotRostered: '{event} rozpoczyna się {startsAt}. Obecnie nie ma cię w rosterze.',
      notRostered: 'Obecnie nie ma cię w rosterze na {event}.',
      eventTimeRostered:
        '{event} rozpoczyna się {startsAt}. Jesteś w {roster}. Rola: {role}; miejsce: {slot}; status: {status}.',
      rostered:
        'Jesteś w rosterze na {event}, w {roster}. Rola: {role}; miejsce: {slot}; status: {status}.',
      contentSubjectMissing: 'Podaj Wydarzenie lub temat poradnika Sojuszu, który mam sprawdzić.',
      contentNotFound: 'Nie znalazłem opublikowanej treści Sojuszu pasującej do „{subject}”.',
      contentFound: 'Strategia Sojuszu — {title}: {excerpt}',
      observationSubjectMissing: 'Podaj Sojusz lub temat obserwacji, który mam sprawdzić.',
      observationNotFound: 'Nie znalazłem autoryzowanej obserwacji pasującej do „{subject}”.',
      observationFound: 'Obserwacja — {title}: {observation}',
      gameFactKnown:
        'Dane gry — {title}. Poniższe wartości ze źródłami pochodzą ze zbioru {datasetVersion}.',
      gameFactUnknown:
        'Dane gry — nie mam obsługiwanej znanej wartości dla {title}. Nie będę zgadywać.',
      gameFactConflicting:
        'Dane gry — utrzymywane dowody dla {title} są sprzeczne. Nie wybiorę wartości za Ciebie.',
      participationNone: 'Brak zapisanego RSVP lub rejestracji dla Ciebie na {event}.',
      participationFound: 'Twój udział w {event} pokazano poniżej.',
      participationWeekNone: 'Nie znaleziono Twojego RSVP ani rejestracji w tym tygodniu.',
      participationNotFound:
        'Nie znaleziono pasującego stanu RSVP, rejestracji ani listy oczekujących.',
      participationWeek: 'Znaleziono {count} rekord(y) udziału w tym tygodniu.',
      participationList: 'Znaleziono {count} pasujące rekord(y) udziału.',
      battlePlanNotFound:
        'Nie znaleziono nadchodzącego autoryzowanego wydarzenia z Twoim przydziałem.',
      battlePlanNone: 'Nie masz obecnie przydziału planu bitwy dla {event}.',
      battlePlanOne: 'Masz jeden przydział planu bitwy dla {event}.',
      battlePlanMany: 'Masz {count} przydziały planu bitwy dla {event}.',
      transferNotInScope: 'Nie mogę podać gotowości transferu z obecnego autoryzowanego zakresu.',
      transferStatus:
        'Twoja ocena transferu to {outcome}. Wymagania wyliczone przez właściciela są poniżej.',
      territoryPlanNotFound: 'Do {event} nie dołączono opublikowanej rewizji planu terytorium.',
      territoryPlanAmbiguous:
        'Do {event} dołączono kilka opublikowanych rewizji planu terytorium; nie wybiorę jednej po cichu.',
      territoryPlanFound: '{event} używa {planName}, rewizja {revisionNumber}, do {purpose}.',
      rosterWriteHandoff:
        'Nie mogę zmienić rosteru tutaj. Otwórz zwykły przepływ {event}, aby wprowadzić zmianę.',
      ownerEventWriteHandoff:
        'Nie mogę dokonać tej zmiany z poziomu Asystenta. Otwórz normalny przepływ pracy właściciela {event}, aby kontynuować.',
      ownerWriteHandoff:
        'Nie mogę zmienić stanu {owner} z poziomu Asystenta. Aktualne autoryzowane fakty są pokazane poniżej; otwórz przepływ pracy właściciela, aby kontynuować.',
      allianceCommandAttention:
        'Przegląd oficera ma {count} faktycznie wymagających uwagi elementów. Autoryzowane źródła są pokazane poniżej.',
      allianceCommandAttentionNotAvailable:
        'Nie ma autoryzowanych faktów oficerskich dla tego sojuszu.',
      eventReadiness:
        'Następne zweryfikowane zdarzenie ma {count} blokujących elementów gotowości. Jego projekcja właściciela jest pokazana poniżej.',
      eventReadinessNotAvailable:
        'Nie mogłem znaleźć pasującej zweryfikowanej gotowości na zdarzenie w przeglądzie upoważnionych oficerów.',
      rallyGaps:
        'Następne zweryfikowane wydarzenie ma {count} luk w gotowości do Rajdu. Fakty dotyczące Rally są przedstawione poniżej.',
      rallyGapsNotAvailable:
        'Nie mogłem znaleźć informacji o gotowości do Rajdu dla pasującego zweryfikowanego wydarzenia.',
      bearHuntHistory:
        'Znalazłem {count} ostatnich autoryzowanych rekordów biegu Bear Hunt. Zarejestrowane i zaakceptowane stany dowodów pozostają wyraźne poniżej.',
      bearHuntHistoryNotAvailable:
        'Nie mogłem znaleźć autoryzowanego ukończonego biegu Bear Hunt z obsługiwaną historią.',
      progressionFreshness:
        '{count} obserwacje gubernatora są przestarzałe lub brakuje ich w autoryzowanej projekcji świeżości listy.',
      progressionFreshnessNotAvailable:
        'Świeżość obserwacji gubernatora nie jest dostępna w przeglądzie upoważnionego oficera.',
      transferVerification:
        '{count} Uczestnik(-y) transferu wymaga(-ją) weryfikacji lub są zablokowany(-i) w ocenie właściciela.',
      transferVerificationNotAvailable:
        'Nie ma aktualnie autoryzowanego przeniesienia projekcji weryfikacji.',
      territoryComparison:
        'Autoryzowane porównanie Terytorium zawiera {count} zaobserwowanych różnic. Brakujące i niezgodne obserwacje pozostają wyraźnie poniżej.',
      territoryComparisonNotAvailable:
        'Nie ma autoryzowanego opublikowanego porównania terytoriów.',
      allianceSettings:
        'Aktualne ustawienia sojuszu to {name}, nazwa URL {slug}, język {language} i strefa czasowa {timezone}.',
      allianceGovernanceHistory:
        'Znalazłem {count} ostatnich autoryzowanych wydarzeń związanych z zarządzaniem sojuszem. Są to fakty audytu właściciela tylko do odczytu.',
      allianceRosterReconciliation:
        'Ostatnio zaakceptowana obserwacja listy ma {needsReview} elementów wymagających przeglądu i {matched} pasujące do bieżącego członkostwa.',
      allianceRosterReconciliationNotAvailable:
        'Nie ma jeszcze akceptowanych obserwacji składu sojuszu, które można by porównać.',
    },
    possibleMatches: 'Możliwe dopasowania',
    detailsHeading: 'Szczegóły odpowiedzi',
    requirementsHeading: 'Wymagania transferu',
    datasetVersion: 'Wersja zbioru danych',
    evidenceStatus: 'Stan dowodów',
    checksum: 'Suma kontrolna',
    revisionLabel: 'Rewizja {revision}',
    participationSummary: 'RSVP: {response} · Rejestracja: {registration}',
    waitlistPosition: 'Pozycja na liście oczekujących: {position}',
    teamLabel: 'Zespół: {team}',
    handoffs: {
      openRoster: 'Otwórz roster wydarzenia',
      openOwnerWorkflow: 'Otwarty właściciel workflow',
    },
    transferRequirements: {
      window_phase: 'Faza okna transferu',
      transfer_group: 'Grupa transferowa',
      power_cap: 'Limit mocy',
      invitation: 'Zaproszenie',
      transfer_passes: 'Przepustki transferowe',
      in_game_rules: 'Zasady w grze',
    },
    transferStates: {
      met: 'Spełnione',
      unmet: 'Niespełnione',
      unknown: 'Nieznane',
      stale: 'Nieaktualne',
      conflicting: 'Sprzeczne',
      not_applicable: 'Nie dotyczy',
    },
    requirementValues: 'Aktualne: {actual} · Wymagane: {required}',
  },
  intelligenceChange: {
    title: 'Ostatnie zmiany wywiadowcze',
    subtitle:
      'Zmiany z podaniem źródeł, wyprowadzone z autoryzowanej historii. Opisują obserwacje, nie zamiary strategiczne.',
    empty: 'Brak istotnych zmian wywiadowczych dla tego zakresu.',
    source: 'Źródło',
    observed: 'Zaobserwowano',
    baseline: 'Punkt odniesienia',
    current: 'Bieżące',
    previous: 'Poprzednie',
    viewSource: 'Zobacz źródło',
    material: 'Istotna zmiana',
    attention: 'Wymaga weryfikacji',
  },
} satisfies MessageCatalogue;

export default messages;
