import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    participantPagination: '参加者のページ',
    participantPageSummary: 'このページは {count} 人、この表示全体は {total} 人です。',
    participantPageOrder: '固定の登録 ID 順です。合計は表示全体を対象とします。',
    participantPageUnavailable:
      '参加者のページを読み込めませんでした。表示中のページは変更されていません。',
    retryParticipantPage: 'ページを再読み込み',
    participantFilterPageOnly:
      'この参加条件フィルターは現在のページに適用されます。全参加者を確認するには、各ページを順に表示してください。',
    workflowHistoryUnavailable: '移転手順の履歴を読み込めませんでした。',
    workflowHistoryTotal: 'この履歴の記録数：{count}件。',
    observationHistoryUnavailable: '観測履歴を読み込めませんでした。',
    reloadHistory: '履歴を再読み込み',
    eyebrow: '王国移民',
    title: '移民計画',
    readinessBoard: '準備状況',
    completion: '結果',
    manageTransfers: '移民を管理',
    currentCycle: '現在のサイクル',
    participants: '参加者',
    incoming: '転入',
    outgoing: '転出',
    staying: '残留',
    transferGroups: '移民グループ',
    player: '総督',
    gamePlayerId: '総督のゲームID',
    readinessTitle: '移民準備状況',
    completionTitle: '移民結果',
    recordCompletion: '移民結果を記録',
    rosterHandoffRecorded: '同盟ロスターを更新済み',
    completedStatus: '完了',
    notCompletedStatus: '未完了',
    readinessReady: '準備完了',
    readinessBlocked: '問題あり',
    readinessConfirmed: '確認済み',
  },
} satisfies MessageCatalogue;

export default messages;
