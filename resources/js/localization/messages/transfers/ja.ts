import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
