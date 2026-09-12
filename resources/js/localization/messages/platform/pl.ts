import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} rekordów',
    historyUnavailable: 'Nie udało się wczytać tej strony.',
    retryPage: 'Ponów wczytanie strony',
    title: 'Administracja platformą',
    backDashboard: 'Wróć do strony głównej',
    localizationRuntime: 'Języki',
  },
} satisfies MessageCatalogue;

export default messages;
