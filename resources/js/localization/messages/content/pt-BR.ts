import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '{count} registros nesta página · {total} no total',
    collectionFailed: 'Não foi possível carregar esta lista. Tente novamente pela primeira página.',
    selectedUnavailable: 'Item selecionado indisponível',
    reviewOnThisPage: 'Esta lista de revisão abrange a página atual do catálogo.',
    allStatuses: 'Todos os estados',
    retryCandidateSummary:
      '{selected} de {total} falhas abaixo do limite de tentativas são exibidas para tentar novamente.',
    eyebrow: 'Conteúdo da aliança',
    hubTitle: 'Central de conteúdo',
    manageContent: 'Gerenciar conteúdo',
    results: 'Resultados',
    publicItems: 'Público',
    memberItems: 'Somente membros',
    categories: 'Categorias',
    search: 'Pesquisar conteúdo da aliança',
    type: 'Tipo de conteúdo',
    category: 'Categoria',
    locale: 'Idioma',
    applyFilters: 'Aplicar filtros',
    clear: 'Limpar',
    publishedContent: 'Conteúdo publicado',
    publicProfile: 'Perfil público da aliança',
    createContent: 'Criar conteúdo',
    editContent: 'Editar conteúdo',
    mediaLibrary: 'Biblioteca de mídia',
    contentInventory: 'Inventário de conteúdo',
  },
} satisfies MessageCatalogue;

export default messages;
