import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} elementi in questa pagina · {total} totali',
    collectionFailed: 'Impossibile caricare l’elenco. Riprova dalla prima pagina.',
    selectedUnavailable: 'Elemento selezionato non disponibile',
    reviewOnThisPage: 'Questo elenco di revisione riguarda la pagina attuale del catalogo.',
    allStatuses: 'Tutti gli stati',
    retryCandidateSummary:
      'Mostrati {selected} di {total} errori sotto il limite di tentativi per riprovare.',
    eyebrow: 'Contenuti alleanza',
    hubTitle: 'Hub contenuti',
    manageContent: 'Gestisci contenuti',
    results: 'Risultati',
    publicItems: 'Pubblico',
    memberItems: 'Solo membri',
    categories: 'Categorie',
    search: 'Cerca contenuti alleanza',
    type: 'Tipo di contenuto',
    category: 'Categoria',
    locale: 'Lingua',
    applyFilters: 'Applica filtri',
    clear: 'Cancella',
    publishedContent: 'Contenuti pubblicati',
    publicProfile: 'Profilo pubblico alleanza',
    createContent: 'Crea contenuto',
    editContent: 'Modifica contenuto',
    mediaLibrary: 'Libreria media',
    contentInventory: 'Inventario contenuti',
  },
} satisfies MessageCatalogue;

export default messages;
