import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} wpisów na tej stronie · łącznie {total}',
    collectionFailed: 'Nie udało się załadować listy. Spróbuj ponownie od pierwszej strony.',
    selectedUnavailable: 'Wybrany element jest niedostępny',
    reviewOnThisPage: 'Ta lista przeglądu obejmuje bieżącą stronę katalogu.',
    allStatuses: 'Wszystkie stany',
    retryCandidateSummary:
      'Wyświetlono {selected} z {total} błędów poniżej limitu prób do ponowienia.',
    eyebrow: 'Treści sojuszu',
    hubTitle: 'Centrum treści',
    manageContent: 'Zarządzaj treścią',
    results: 'Wyniki',
    publicItems: 'Publiczne',
    memberItems: 'Tylko członkowie',
    categories: 'Kategorie',
    search: 'Szukaj treści sojuszu',
    type: 'Typ treści',
    category: 'Kategoria',
    locale: 'Język',
    applyFilters: 'Zastosuj filtry',
    clear: 'Wyczyść',
    publishedContent: 'Opublikowane treści',
    publicProfile: 'Publiczny profil sojuszu',
    createContent: 'Utwórz treść',
    editContent: 'Edytuj treść',
    mediaLibrary: 'Biblioteka mediów',
    contentInventory: 'Spis treści',
  },
} satisfies MessageCatalogue;

export default messages;
