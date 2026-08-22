import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: '联盟招募',
    title: '招募',
    candidates: '候选人',
    accepted: '已接受',
    joined: '已加入',
    pipeline: '招募',
    backToPipeline: '返回招募',
    stage: '阶段',
    source: '来源',
    submitted: '提交时间',
    nextAction: '下一步',
    bulkActions: '候选人阶段变更',
    selectedCandidates: '已选择 {count} 名候选人',
    bulkPreviewHelp: '应用更改前先检查哪些候选人可以移动。未满足条件的候选人不会发生变化。',
    previewBulkAction: '检查阶段变更',
    bulkPreview: '阶段变更预览',
    bulkPreviewSummary: '{ready} 名可以更新，{blocked} 名需要检查或已经处于目标阶段。',
    confirmBulkTitle: '确认阶段变更',
    confirmBulkDescription: '将 {count} 名符合条件的候选人移动到 {stage}？',
    confirmBulkAction: '更新符合条件的候选人',
    bulkResult: '阶段变更结果',
    bulkResultSummary: '{succeeded} 名已更新。{failed} 名需要检查。{skipped} 名已经是最新状态。',
    failedItemsSelected: '无法更新的候选人会保持选中，方便你检查。',
    settings: '申请设置',
    questions: '申请问题',
    onboarding: '入盟清单',
    choosePlayer: '选择总督',
    privateNotes: '招募人员私密备注',
    stageHistory: '阶段历史',
  },
} satisfies MessageCatalogue;

export default messages;
