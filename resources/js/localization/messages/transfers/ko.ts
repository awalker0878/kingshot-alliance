import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: '선택 항목 검색',
    choicePageSummary: '이 페이지에 {count}개, 일치하는 항목 총 {total}개. 고정 레코드 ID순입니다.',
    choiceUnavailable: '선택한 항목을 더 이상 사용할 수 없습니다.',
    choicesFailed: '선택 항목을 불러오지 못했습니다. 선택과 표시된 페이지는 유지됩니다.',
    retryChoices: '다시 불러오기',

    participantPagination: '참가자 페이지',
    participantPageSummary: '현재 페이지에 {count}명, 이 보기 전체에 {total}명이 있습니다.',
    participantPageOrder: '변하지 않는 등록 ID순입니다. 합계는 보기 전체를 포함합니다.',
    participantPageUnavailable:
      '참가자 페이지를 불러오지 못했습니다. 현재 페이지는 변경되지 않았습니다.',
    retryParticipantPage: '페이지 다시 시도',
    participantFilterPageOnly:
      '이 자격 필터는 현재 페이지에 적용됩니다. 모든 참가자를 확인하려면 다음 페이지로 이동하세요.',
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
