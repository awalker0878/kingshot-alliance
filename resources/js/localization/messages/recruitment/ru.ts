import type { MessageCatalogue } from '../../types';

const messages = {
  recruitment: {
    eyebrow: 'Набор в альянс',
    title: 'Набор',
    candidates: 'Кандидаты',
    accepted: 'Приняты',
    joined: 'Вступили',
    pipeline: 'Набор',
    backToPipeline: 'Вернуться к набору',
    stage: 'Этап',
    source: 'Источник',
    submitted: 'Подано',
    nextAction: 'Следующее действие',
    bulkActions: 'Изменение этапов кандидатов',
    selectedCandidates: 'Выбрано кандидатов: {count}',
    bulkPreviewHelp:
      'Перед применением изменения проверьте, кого можно перевести. Не подходящие кандидаты останутся без изменений.',
    previewBulkAction: 'Проверить изменение этапа',
    bulkPreview: 'Предпросмотр изменения этапа',
    bulkPreviewSummary:
      '{ready} можно обновить; {blocked} требуют проверки или уже находятся на нужном этапе.',
    confirmBulkTitle: 'Подтвердить изменение этапа',
    confirmBulkDescription: 'Перевести {count} подходящих кандидатов на этап {stage}?',
    confirmBulkAction: 'Обновить подходящих кандидатов',
    bulkResult: 'Результат изменения этапа',
    bulkResultSummary:
      '{succeeded} обновлено. {failed} требуют проверки. {skipped} уже были актуальны.',
    failedItemsSelected:
      'Кандидаты, которых не удалось обновить, остаются выбранными для проверки.',
    settings: 'Настройки заявки',
    questions: 'Вопросы заявки',
    onboarding: 'Список адаптации',
    choosePlayer: 'Выберите Губернатора',
    privateNotes: 'Приватные заметки рекрутера',
    stageHistory: 'История этапов',
  },
} satisfies MessageCatalogue;

export default messages;
