import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
