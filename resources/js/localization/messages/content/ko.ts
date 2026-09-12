import type { MessageCatalogue } from '../../types';

const messages = {
  contentExperience: {
    pageRecords: '이 페이지의 기록 {count}개 · 총 {total}개',
    collectionFailed: '목록을 불러올 수 없습니다. 첫 페이지에서 다시 시도하세요.',
    selectedUnavailable: '선택한 항목을 사용할 수 없습니다',
    reviewOnThisPage: '이 검토 목록은 현재 카탈로그 페이지에 해당합니다.',
    allStatuses: '모든 상태',
    retryCandidateSummary: '시도 한도 미만인 실패 {total}건 중 재시도할 {selected}건을 표시합니다.',
    eyebrow: '연맹 콘텐츠',
    hubTitle: '콘텐츠 허브',
    manageContent: '콘텐츠 관리',
    results: '결과',
    publicItems: '공개',
    memberItems: '멤버 전용',
    categories: '카테고리',
    search: '연맹 콘텐츠 검색',
    type: '콘텐츠 유형',
    category: '카테고리',
    locale: '언어',
    applyFilters: '필터 적용',
    clear: '지우기',
    publishedContent: '게시된 콘텐츠',
    publicProfile: '연맹 공개 프로필',
    createContent: '콘텐츠 만들기',
    editContent: '콘텐츠 편집',
    mediaLibrary: '미디어 라이브러리',
    contentInventory: '콘텐츠 목록',
  },
} satisfies MessageCatalogue;

export default messages;
