import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '本頁 {count} 筆記錄 · 共 {total} 筆',
    collectionFailed: '無法載入清單。請從第一頁重試。',
    selectedUnavailable: '所選項目無法使用',
    reviewOnThisPage: '此審查清單僅涵蓋目前的目錄頁。',
    allStatuses: '所有狀態',
    retryCandidateSummary: '共 {total} 次失敗尚未達到嘗試上限，顯示其中 {selected} 次供重試。',
    eyebrow: '聯盟內容',
    hubTitle: '內容中心',
    manageContent: '管理內容',
    results: '結果',
    publicItems: '公開',
    memberItems: '僅成員',
    categories: '分類',
    search: '搜尋聯盟內容',
    type: '內容類型',
    category: '分類',
    locale: '語言',
    applyFilters: '套用篩選',
    clear: '清除',
    publishedContent: '已發布內容',
    publicProfile: '聯盟公開資料',
    createContent: '建立內容',
    editContent: '編輯內容',
    mediaLibrary: '媒體庫',
    contentInventory: '內容清單',
  },
} satisfies MessageCatalogue;

export default messages;
