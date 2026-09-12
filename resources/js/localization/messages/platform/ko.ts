import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: '선택한 항목을 더 이상 사용할 수 없습니다.',
    searchChoices: '선택 항목 검색',
    choicePageSummary: '이 페이지에 {count}개, 일치하는 항목 총 {total}개. 고정 레코드 ID순입니다.',
    choicesFailed: '선택 항목을 불러오지 못했습니다. 선택과 표시된 페이지는 유지됩니다.',
    retryChoices: '다시 불러오기',
    recoveryFailed: '복구를 완료하지 못했습니다. 다시 시도해 주세요.',
    recordCount: '기록 {count}개',
    historyUnavailable: '이 페이지를 불러올 수 없습니다.',
    retryPage: '페이지 다시 시도',
    title: '플랫폼 관리',
    backDashboard: '홈으로 돌아가기',
    administrators: '플랫폼 관리자',
    allianceFleet: '연맹',
    localizationRuntime: '언어',
  },
} satisfies MessageCatalogue;

export default messages;
