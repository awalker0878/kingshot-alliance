import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    workflowHistoryUnavailable: '이전 절차 기록을 불러올 수 없습니다.',
    workflowHistoryTotal: '이 기록의 항목 수: {count}개.',
    observationHistoryUnavailable: '관측 기록을 불러오지 못했습니다.',
    reloadHistory: '기록 새로고침',
    eyebrow: '왕국 이전',
    title: '이전 계획',
    readinessBoard: '준비 상태',
    completion: '결과',
    manageTransfers: '이전 관리',
    currentCycle: '현재 주기',
    participants: '참가자',
    incoming: '전입',
    outgoing: '전출',
    staying: '잔류',
    transferGroups: '이전 그룹',
    player: '총독',
    gamePlayerId: '총독 게임 ID',
    readinessTitle: '이전 준비 상태',
    completionTitle: '이전 결과',
    recordCompletion: '이전 결과 기록',
    rosterHandoffRecorded: '연맹 명단 업데이트됨',
    completedStatus: '완료',
    notCompletedStatus: '미완료',
    readinessReady: '준비됨',
    readinessBlocked: '문제 있음',
    readinessConfirmed: '확인됨',
  },
} satisfies MessageCatalogue;

export default messages;
