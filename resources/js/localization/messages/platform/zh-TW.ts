import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} 筆記錄',
    historyUnavailable: '無法載入此頁面。',
    retryPage: '重試此頁',
    title: '平台管理',
    backDashboard: '返回首頁',
    administrators: '平台管理員',
    allianceFleet: '聯盟',
    localizationRuntime: '語言',
  },
} satisfies MessageCatalogue;

export default messages;
