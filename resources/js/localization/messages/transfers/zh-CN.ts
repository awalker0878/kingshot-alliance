import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    eyebrow: '王国转移',
    title: '转移计划',
    readinessBoard: '准备情况',
    completion: '结果',
    manageTransfers: '管理转移',
    currentCycle: '当前周期',
    participants: '参与者',
    incoming: '转入',
    outgoing: '转出',
    staying: '留下',
    transferGroups: '转移小组',
    player: '总督',
    gamePlayerId: '总督游戏 ID',
    readinessTitle: '转移准备情况',
    completionTitle: '转移结果',
    recordCompletion: '记录转移结果',
    rosterHandoffRecorded: '联盟成员名单已更新',
    completedStatus: '已完成',
    notCompletedStatus: '未完成',
    readinessReady: '已准备',
    readinessBlocked: '受阻',
    readinessConfirmed: '已确认',
  },
} satisfies MessageCatalogue;

export default messages;
