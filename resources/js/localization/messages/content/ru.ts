import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: 'Записей на странице: {count} · всего: {total}',
    collectionFailed: 'Не удалось загрузить список. Повторите с первой страницы.',
    selectedUnavailable: 'Выбранный элемент недоступен',
    reviewOnThisPage: 'Этот список проверки относится к текущей странице каталога.',
    allStatuses: 'Все статусы',
    retryCandidateSummary:
      'Для повтора показано {selected} из {total} ошибок, не достигших лимита попыток.',
    eyebrow: 'Контент альянса',
    hubTitle: 'Центр контента',
    manageContent: 'Управление контентом',
    results: 'Результаты',
    publicItems: 'Публичное',
    memberItems: 'Только участники',
    categories: 'Категории',
    search: 'Поиск контента альянса',
    type: 'Тип контента',
    category: 'Категория',
    locale: 'Язык',
    applyFilters: 'Применить фильтры',
    clear: 'Очистить',
    publishedContent: 'Опубликованный контент',
    publicProfile: 'Публичный профиль альянса',
    createContent: 'Создать контент',
    editContent: 'Редактировать контент',
    mediaLibrary: 'Медиатека',
    contentInventory: 'Список контента',
  },
} satisfies MessageCatalogue;

export default messages;
