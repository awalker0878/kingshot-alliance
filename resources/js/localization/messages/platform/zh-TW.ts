import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: '所選項目已無法使用。',
    searchChoices: '搜尋選項',
    choicePageSummary: '本頁 {count} 個選項，共 {total} 個相符項目。依固定記錄識別碼排序。',
    choicesFailed: '無法載入選項。目前選擇與顯示頁面保持不變。',
    retryChoices: '重新載入',
    recoveryFailed: '無法完成復原。請重試。',
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
