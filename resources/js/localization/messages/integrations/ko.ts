import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    eyebrow: '연맹 통합',
    title: 'API 자격 증명 및 웹훅',
    activeCredentials: '활성 자격 증명',
    activeWebhooks: '활성 웹훅',
    recentDeliveries: '최근 전송',
    apiCredentials: 'API 자격 증명',
    createCredential: '자격 증명 생성',
    revoke: '취소',
    webhookSubscriptions: '웹훅 구독',
    createWebhook: '웹훅 생성',
    deliveryLog: '최근 전송 로그',
    event: '이벤트',
    status: '상태',
    attempts: '시도',
    lastError: '최근 오류',
  },
} satisfies MessageCatalogue;

export default messages;
