import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: '選択した項目は利用できなくなりました。',
    searchChoices: '選択肢を検索',
    choicePageSummary:
      'このページは{count}件、該当する選択肢は全{total}件です。固定レコードID順です。',
    choicesFailed: '選択肢を読み込めませんでした。選択内容と表示ページは変更されていません。',
    retryChoices: '再読み込み',
    recoveryFailed: '復旧を完了できませんでした。もう一度お試しください。',
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
