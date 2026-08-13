import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: 'Интеграции альянса',
    title: 'Учётные данные API и вебхуки',
    activeCredentials: 'Активные учётные данные',
    activeWebhooks: 'Активные вебхуки',
    recentDeliveries: 'Последние доставки',
    apiCredentials: 'Учётные данные API',
    createCredential: 'Создать учётные данные',
    revoke: 'Отозвать',
    webhookSubscriptions: 'Подписки вебхуков',
    createWebhook: 'Создать вебхук',
    deliveryLog: 'Журнал последних доставок',
    event: 'Событие',
    status: 'Статус',
    attempts: 'Попытки',
    lastError: 'Последняя ошибка',
  },
} satisfies MessageCatalogue;

export default messages;
