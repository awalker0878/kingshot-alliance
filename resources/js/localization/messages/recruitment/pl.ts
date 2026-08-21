import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Rekrutacja sojuszu',
    title: 'Rekrutacja',
    candidates: 'Kandydaci',
    accepted: 'Zaakceptowani',
    joined: 'Dołączyli',
    pipeline: 'Rekrutacja',
    backToPipeline: 'Wróć do rekrutacji',
    stage: 'Etap',
    source: 'Źródło',
    submitted: 'Wysłano',
    nextAction: 'Następne działanie',
    bulkActions: 'Zmiany etapów kandydatów',
    selectedCandidates: 'Wybrano {count} kandydatów',
    bulkPreviewHelp: 'Przed zastosowaniem zmiany sprawdź, których kandydatów można przenieść. Kandydaci bez uprawnień pozostaną bez zmian.',
    previewBulkAction: 'Sprawdź zmianę etapu',
    bulkPreview: 'Podgląd zmiany etapu',
    bulkPreviewSummary: '{ready} można zaktualizować, a {blocked} wymaga sprawdzenia lub już znajduje się na docelowym etapie.',
    confirmBulkTitle: 'Potwierdź zmianę etapu',
    confirmBulkDescription: 'Przenieść {count} uprawnionych kandydatów do etapu {stage}?',
    confirmBulkAction: 'Zaktualizuj uprawnionych kandydatów',
    bulkResult: 'Wynik zmiany etapu',
    bulkResultSummary: '{succeeded} zaktualizowano. {failed} wymaga sprawdzenia. {skipped} było już aktualnych.',
    failedItemsSelected: 'Kandydaci, których nie udało się zaktualizować, pozostają zaznaczeni do sprawdzenia.',
    settings: 'Ustawienia aplikacji',
    questions: 'Pytania aplikacyjne',
    onboarding: 'Lista wdrożeniowa',
    choosePlayer: 'Wybierz Gubernatora',
    privateNotes: 'Prywatne notatki rekrutera',
    stageHistory: 'Historia etapów',
  },
} satisfies MessageCatalogue;

export default messages;
