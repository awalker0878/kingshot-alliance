import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} registros en esta página · {total} en total',
    collectionFailed: 'No se pudo cargar esta lista. Reintenta desde la primera página.',
    selectedUnavailable: 'Elemento seleccionado no disponible',
    reviewOnThisPage: 'Esta lista de revisión corresponde a la página actual del catálogo.',
    allStatuses: 'Todos los estados',
    retryCandidateSummary:
      'Se muestran {selected} de {total} fallos por debajo del límite de intentos para reintentar.',
    eyebrow: 'Contenido de la alianza',
    hubTitle: 'Centro de contenido',
    manageContent: 'Gestionar contenido',
    results: 'Resultados',
    publicItems: 'Público',
    memberItems: 'Solo miembros',
    categories: 'Categorías',
    search: 'Buscar contenido de la alianza',
    type: 'Tipo de contenido',
    category: 'Categoría',
    locale: 'Idioma',
    applyFilters: 'Aplicar filtros',
    clear: 'Limpiar',
    publishedContent: 'Contenido publicado',
    publicProfile: 'Perfil público de la alianza',
    createContent: 'Crear contenido',
    editContent: 'Editar contenido',
    mediaLibrary: 'Biblioteca multimedia',
    contentInventory: 'Inventario de contenido',
  },
} satisfies MessageCatalogue;

export default messages;
