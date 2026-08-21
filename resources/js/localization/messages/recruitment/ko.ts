import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: '연맹 모집',
    title: '모집',
    candidates: '지원자',
    accepted: '승인',
    joined: '가입',
    pipeline: '모집',
    backToPipeline: '모집으로 돌아가기',
    stage: '단계',
    source: '출처',
    submitted: '제출',
    nextAction: '다음 작업',
    bulkActions: '지원자 단계 변경',
    selectedCandidates: '지원자 {count}명 선택됨',
    bulkPreviewHelp:
      '변경을 적용하기 전에 이동 가능한 지원자를 확인합니다. 대상이 아닌 지원자는 변경되지 않습니다.',
    previewBulkAction: '단계 변경 확인',
    bulkPreview: '단계 변경 미리보기',
    bulkPreviewSummary:
      '{ready}명은 업데이트할 수 있고 {blocked}명은 확인이 필요하거나 이미 대상 단계입니다.',
    confirmBulkTitle: '단계 변경 확인',
    confirmBulkDescription: '대상 지원자 {count}명을 {stage}(으)로 이동하시겠습니까?',
    confirmBulkAction: '대상 지원자 업데이트',
    bulkResult: '단계 변경 결과',
    bulkResultSummary:
      '{succeeded}명 업데이트됨. {failed}명은 확인이 필요합니다. {skipped}명은 이미 최신 상태였습니다.',
    failedItemsSelected: '업데이트할 수 없었던 지원자는 검토할 수 있도록 선택된 상태로 유지됩니다.',
    settings: '지원 설정',
    questions: '지원 질문',
    onboarding: '온보딩 체크리스트',
    choosePlayer: '총독 선택',
    privateNotes: '모집 담당 비공개 메모',
    stageHistory: '단계 기록',
  },
} satisfies MessageCatalogue;

export default messages;
