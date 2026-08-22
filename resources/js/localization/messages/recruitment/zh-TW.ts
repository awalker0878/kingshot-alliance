import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: '聯盟招募',
    title: '招募',
    candidates: '候選人',
    accepted: '已接受',
    joined: '已加入',
    pipeline: '招募',
    backToPipeline: '返回招募',
    stage: '階段',
    source: '來源',
    submitted: '提交時間',
    nextAction: '下一步',
    bulkActions: '候選人階段變更',
    selectedCandidates: '已選取 {count} 名候選人',
    bulkPreviewHelp: '套用變更前先檢查哪些候選人可以移動。不符合條件的候選人不會變更。',
    previewBulkAction: '檢查階段變更',
    bulkPreview: '階段變更預覽',
    bulkPreviewSummary: '{ready} 名可更新，{blocked} 名需要檢查或已在目標階段。',
    confirmBulkTitle: '確認階段變更',
    confirmBulkDescription: '將 {count} 名符合條件的候選人移至 {stage}？',
    confirmBulkAction: '更新符合條件的候選人',
    bulkResult: '階段變更結果',
    bulkResultSummary: '{succeeded} 名已更新。{failed} 名需要檢查。{skipped} 名已是最新狀態。',
    failedItemsSelected: '無法更新的候選人會保持選取，方便你檢查。',
    settings: '申請設定',
    questions: '申請問題',
    onboarding: '入盟清單',
    choosePlayer: '選擇總督',
    privateNotes: '招募人員私人備註',
    stageHistory: '階段歷史',
  },
} satisfies MessageCatalogue;

export default messages;
