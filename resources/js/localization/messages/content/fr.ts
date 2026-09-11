import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} éléments sur cette page · {total} au total',
    collectionFailed: 'Impossible de charger cette liste. Réessayez depuis la première page.',
    selectedUnavailable: 'Élément sélectionné indisponible',
    reviewOnThisPage: 'Cette liste de révision concerne la page actuelle du catalogue.',
    allStatuses: 'Tous les statuts',
    retryCandidateSummary:
      '{selected} affichés pour une nouvelle tentative sur {total} échecs sous la limite de tentatives.',
    eyebrow: 'Contenu de l’alliance',
    hubTitle: 'Centre de contenu',
    manageContent: 'Gérer le contenu',
    results: 'Résultats',
    memberItems: 'Membres seulement',
    categories: 'Catégories',
    search: 'Rechercher le contenu de l’alliance',
    type: 'Type de contenu',
    category: 'Catégorie',
    locale: 'Langue',
    applyFilters: 'Appliquer les filtres',
    clear: 'Effacer',
    publishedContent: 'Contenu publié',
    publicProfile: 'Profil public de l’alliance',
    createContent: 'Créer du contenu',
    editContent: 'Modifier le contenu',
    mediaLibrary: 'Médiathèque',
    contentInventory: 'Inventaire du contenu',
  },
} satisfies MessageCatalogue;

export default messages;
