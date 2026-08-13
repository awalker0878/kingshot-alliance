import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: '同盟インテグレーション',
    title: 'API認証情報とWebhook',
    activeCredentials: '有効な認証情報',
    activeWebhooks: '有効なWebhook',
    recentDeliveries: '最近の配信',
    apiCredentials: 'API認証情報',
    createCredential: '認証情報を作成',
    revoke: '無効化',
    webhookSubscriptions: 'Webhook購読',
    createWebhook: 'Webhookを作成',
    deliveryLog: '最近の配信ログ',
    event: 'イベント',
    status: '状態',
    attempts: '試行回数',
    lastError: '最新エラー',
  },
} satisfies MessageCatalogue;

export default messages;
