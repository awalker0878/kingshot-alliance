import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    recordCount: '{count} 筆記錄',
    historyUnavailable: '無法載入此頁面。',
    retryPage: '重試此頁',
    expired: '已過期',
    deliveryQueued: '已排入佇列',
    deliveryHistory: '傳送記錄',

    eyebrow: '聯盟整合',
    title: 'API 憑證與 Webhook',
    activeCredentials: '有效憑證',
    activeWebhooks: '有效 Webhook',
    recentDeliveries: '最近投遞',
    apiCredentials: 'API 憑證',
    createCredential: '建立憑證',
    revoke: '撤銷',
    webhookSubscriptions: 'Webhook 訂閱',
    createWebhook: '建立 Webhook',
    deliveryLog: '最近投遞記錄',
    event: '事件',
    status: '狀態',
    attempts: '嘗試次數',
    lastError: '最近錯誤',
  },
} satisfies MessageCatalogue;

export default messages;
