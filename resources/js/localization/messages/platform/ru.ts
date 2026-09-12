import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'Выбранный вариант больше недоступен.',
    searchChoices: 'Поиск вариантов',
    choicePageSummary:
      '{count} вариантов на странице; найдено {total}. Порядок по постоянному идентификатору.',
    choicesFailed: 'Не удалось загрузить варианты. Выбор и текущая страница сохранены.',
    retryChoices: 'Повторить загрузку',
    recoveryFailed: 'Не удалось завершить восстановление. Повторите попытку.',
    recordCount: 'Записей: {count}',
    historyUnavailable: 'Не удалось загрузить эту страницу.',
    retryPage: 'Повторить загрузку',
    title: 'Администрирование платформы',
    backDashboard: 'Вернуться на главную',
    localizationRuntime: 'Языки',
  },
} satisfies MessageCatalogue;

export default messages;
