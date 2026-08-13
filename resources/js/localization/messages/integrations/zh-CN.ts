import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: '联盟集成',
    title: 'API 凭据与 Webhook',
    activeCredentials: '有效凭据',
    activeWebhooks: '有效 Webhook',
    recentDeliveries: '最近投递',
    apiCredentials: 'API 凭据',
    createCredential: '创建凭据',
    revoke: '撤销',
    webhookSubscriptions: 'Webhook 订阅',
    createWebhook: '创建 Webhook',
    deliveryLog: '最近投递日志',
    event: '事件',
    status: '状态',
    attempts: '尝试次数',
    lastError: '最近错误',
  },
} satisfies MessageCatalogue;

export default messages;
