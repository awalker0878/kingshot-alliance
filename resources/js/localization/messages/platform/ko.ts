import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
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
