import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: 'Записей: {count}',
    historyUnavailable: 'Не удалось загрузить эту страницу.',
    retryPage: 'Повторить загрузку',
    title: 'Администрирование платформы',
    backDashboard: 'Вернуться на главную',
    localizationRuntime: 'Языки',
  },
} satisfies MessageCatalogue;

export default messages;
