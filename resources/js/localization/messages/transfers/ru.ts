import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Поиск вариантов',
    choicePageSummary:
      '{count} вариантов на странице; найдено {total}. Порядок по постоянному идентификатору.',
    choiceUnavailable: 'Выбранный вариант больше недоступен.',
    choicesFailed: 'Не удалось загрузить варианты. Выбор и текущая страница сохранены.',
    retryChoices: 'Повторить загрузку',

    participantPagination: 'Страницы участников',
    participantPageSummary: '{count} участников на этой странице; {total} в этом представлении.',
    participantPageOrder:
      'Порядок по неизменяемому ID регистрации. Итоги охватывают всё представление.',
    participantPageUnavailable:
      'Не удалось загрузить страницу участников. Отображаемая страница не изменилась.',
    retryParticipantPage: 'Повторить загрузку',
    participantFilterPageOnly:
      'Этот фильтр допуска действует на текущую страницу. Просмотрите остальные страницы, чтобы проверить всех участников.',
    workflowHistoryUnavailable: 'Не удалось загрузить историю процесса переноса.',
    workflowHistoryTotal: 'Записей в этой истории: {count}.',
    observationHistoryUnavailable: 'Не удалось загрузить историю наблюдений.',
    reloadHistory: 'Перезагрузить историю',
    eyebrow: 'Перенос Королевства',
    title: 'Планирование переноса',
    readinessBoard: 'Готовность',
    completion: 'Результат',
    manageTransfers: 'Управление переносами',
    currentCycle: 'Текущий цикл',
    participants: 'Участники',
    incoming: 'Входящие',
    outgoing: 'Исходящие',
    staying: 'Остаётся',
    transferGroups: 'Группы переноса',
    player: 'Губернатор',
    gamePlayerId: 'Игровой ID Губернатора',
    readinessTitle: 'Готовность к переносу',
    completionTitle: 'Результат переноса',
    recordCompletion: 'Записать результат переноса',
    rosterHandoffRecorded: 'Состав альянса обновлён',
    completedStatus: 'Завершено',
    notCompletedStatus: 'Не завершено',
    readinessReady: 'Готов',
    readinessBlocked: 'Заблокирован',
    readinessConfirmed: 'Подтверждён',
  },
} satisfies MessageCatalogue;

export default messages;
