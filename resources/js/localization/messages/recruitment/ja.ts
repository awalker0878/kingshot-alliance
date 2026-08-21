import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: '同盟募集',
    title: '募集',
    candidates: '候補者',
    accepted: '承認',
    joined: '加入',
    pipeline: '募集',
    backToPipeline: '募集に戻る',
    stage: '段階',
    source: '流入元',
    submitted: '応募日時',
    nextAction: '次の対応',
    bulkActions: '候補者の段階変更',
    selectedCandidates: '{count}人の候補者を選択',
    bulkPreviewHelp: '変更前に移動可能な候補者を確認します。対象外の候補者は変更されません。',
    previewBulkAction: '段階変更を確認',
    bulkPreview: '段階変更のプレビュー',
    bulkPreviewSummary: '{ready}人は更新可能、{blocked}人は確認が必要か、すでに対象段階です。',
    confirmBulkTitle: '段階変更を確認',
    confirmBulkDescription: '対象となる{count}人の候補者を{stage}へ移動しますか？',
    confirmBulkAction: '対象候補者を更新',
    bulkResult: '段階変更結果',
    bulkResultSummary:
      '{succeeded}人を更新しました。{failed}人は確認が必要です。{skipped}人はすでに最新でした。',
    failedItemsSelected: '更新できなかった候補者は確認できるよう選択されたままになります。',
    settings: '応募設定',
    questions: '応募質問',
    onboarding: 'オンボーディング一覧',
    choosePlayer: '総督を選択',
    privateNotes: '採用担当の非公開メモ',
    stageHistory: '段階履歴',
  },
} satisfies MessageCatalogue;

export default messages;
