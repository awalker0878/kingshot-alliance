import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: '王國轉移',
    title: '轉移計畫',
    readinessBoard: '準備情況',
    completion: '結果',
    manageTransfers: '管理轉移',
    currentCycle: '目前週期',
    participants: '參與者',
    incoming: '轉入',
    outgoing: '轉出',
    staying: '留下',
    transferGroups: '轉移小組',
    player: '總督',
    gamePlayerId: '總督遊戲 ID',
    readinessTitle: '轉移準備情況',
    completionTitle: '轉移結果',
    recordCompletion: '記錄轉移結果',
    rosterHandoffRecorded: '聯盟成員名單已更新',
    completedStatus: '已完成',
    notCompletedStatus: '未完成',
    readinessReady: '已準備',
    readinessBlocked: '受阻',
    readinessConfirmed: '已確認',
  },
} satisfies MessageCatalogue;

export default messages;
