import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'Wybrana opcja nie jest już dostępna.',
    searchChoices: 'Szukaj opcji',
    choicePageSummary:
      '{count} opcji na tej stronie; {total} pasuje. Kolejność według stałego identyfikatora.',
    choicesFailed: 'Nie udało się wczytać opcji. Wybór i wyświetlana strona pozostają bez zmian.',
    retryChoices: 'Ponów wczytanie',
    recoveryFailed: 'Nie udało się ukończyć odzyskiwania. Spróbuj ponownie.',
    recordCount: '{count} rekordów',
    historyUnavailable: 'Nie udało się wczytać tej strony.',
    retryPage: 'Ponów wczytanie strony',
    title: 'Administracja platformą',
    backDashboard: 'Wróć do strony głównej',
    localizationRuntime: 'Języki',
  },
} satisfies MessageCatalogue;

export default messages;
