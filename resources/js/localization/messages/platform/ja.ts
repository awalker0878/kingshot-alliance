import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} 件の記録',
    historyUnavailable: 'このページを読み込めませんでした。',
    retryPage: 'ページを再読み込み',
    title: 'プラットフォーム管理',
    backDashboard: 'ホームに戻る',
    administrators: 'プラットフォーム管理者',
    allianceFleet: '同盟',
    localizationRuntime: '言語',
  },
} satisfies MessageCatalogue;

export default messages;
