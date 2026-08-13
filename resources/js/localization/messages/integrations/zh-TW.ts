import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
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
