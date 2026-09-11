import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: 'このページは{count}件 · 全{total}件',
    collectionFailed: '一覧を読み込めませんでした。最初のページから再試行してください。',
    selectedUnavailable: '選択した項目は利用できません',
    reviewOnThisPage: 'この確認リストは現在のカタログページを対象としています。',
    allStatuses: 'すべての状態',
    retryCandidateSummary:
      '試行回数の上限未満の失敗 {total} 件のうち、再試行用に {selected} 件を表示しています。',
    eyebrow: '同盟コンテンツ',
    hubTitle: 'コンテンツハブ',
    manageContent: 'コンテンツ管理',
    results: '結果',
    publicItems: '公開',
    memberItems: 'メンバー限定',
    categories: 'カテゴリ',
    search: '同盟コンテンツを検索',
    type: 'コンテンツ種別',
    category: 'カテゴリ',
    locale: '言語',
    applyFilters: 'フィルター適用',
    clear: 'クリア',
    publishedContent: '公開済みコンテンツ',
    publicProfile: '同盟公開プロフィール',
    createContent: 'コンテンツ作成',
    editContent: 'コンテンツ編集',
    mediaLibrary: 'メディアライブラリ',
    contentInventory: 'コンテンツ一覧',
  },
} satisfies MessageCatalogue;

export default messages;
