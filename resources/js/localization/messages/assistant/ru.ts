import type { MessageCatalogue } from '../../types';
const messages = {
  assistant: {
    navigation: 'Помощник',
    title: 'Спросите свой Альянс',
    eyebrow: 'Помощник Альянса · Авторизованные ответы',
    subtitle:
      'Спрашивайте о Событиях, своем составе, руководствах Альянса и наблюдениях. Ответы основаны на источниках, которые вам уже разрешено видеть.',
    authorizationHint: 'Ответы используют только данные Альянса, к которым у вас есть доступ.',
    tryAsking: 'Попробуйте спросить',
    conversation: 'Диалог с Помощником Альянса',
    youAsked: 'Вы спросили',
    possibleEvents: 'Возможные События',
    openEvent: 'Открыть Событие',
    sourcesHeading: 'Использованные источники',
    sourceTime: 'Время источника: {time}',
    questionLabel: 'Спросите свой Альянс',
    questionPlaceholder: 'Во сколько Swordland и есть ли я в составе?',
    inputHint: '{count}/{max} символов · Enter — спросить · Shift+Enter — новая строка',
    asking: 'Проверяю источники…',
    ask: 'Спросить',
    notRecorded: 'Не указано',
    classifications: {
      operational_fact: 'Операционный факт',
      game_fact: 'Данные игры',
      alliance_strategy: 'Стратегия Альянса',
      observation: 'Наблюдение',
    },
    sources: {
      event: 'Событие',
      roster: 'Состав',
      alliance_content: 'Руководство Альянса',
      observation: 'Наблюдение',
      game_fact: 'Данные игры',
    },
    prompts: {
      swordland: 'Во сколько Swordland и есть ли я в составе?',
      nextEvent: 'Какое у меня следующее Событие?',
      bearGuide: 'Что говорит наше руководство Bear Hunt?',
      observation: 'Что мы наблюдали у противника?',
    },
    answers: {
      help: 'Я могу отвечать по данным Событий, вашего состава, руководств Альянса и разрешенных наблюдений. Я не использую сведения о KingShot без источника.',
      unsupported:
        'Я могу отвечать только по разрешенным Событиям, вашему составу, руководствам Альянса и наблюдениям. Я не могу вносить изменения отсюда.',
      unavailable: 'Помощник Альянса сейчас не может проверить источники. Попробуйте снова.',
      rateLimited: 'Вы задаете вопросы слишком быстро. Попробуйте чуть позже.',
      validationError: 'Введите вопрос длиной от 2 до {max} символов.',
      noUpcomingEvent: 'Я не нашел предстоящего События, которое вам разрешено видеть.',
      eventSubjectMissing: 'Укажите Событие, которое нужно проверить.',
      eventNotFound: 'Я не нашел разрешенного предстоящего События, соответствующего «{subject}».',
      eventAmbiguous:
        'Я нашел несколько Событий, соответствующих «{subject}». Откройте нужное ниже.',
      eventTime: '{event} начинается {startsAt}.',
      eventTimeNotRostered: '{event} начинается {startsAt}. Сейчас вас нет в составе.',
      notRostered: 'Сейчас вас нет в составе на {event}.',
      eventTimeRostered:
        '{event} начинается {startsAt}. Вы включены в {roster}. Роль: {role}; слот: {slot}; статус: {status}.',
      rostered:
        'Вы включены в состав на {event}, в {roster}. Роль: {role}; слот: {slot}; статус: {status}.',
      contentSubjectMissing:
        'Укажите Событие или тему руководства Альянса, которое нужно проверить.',
      contentNotFound: 'Я не нашел опубликованный материал Альянса, соответствующий «{subject}».',
      contentFound: 'Стратегия Альянса — {title}: {excerpt}',
      observationSubjectMissing: 'Укажите Альянс или тему наблюдения, которую нужно проверить.',
      observationNotFound: 'Я не нашел разрешенное наблюдение, соответствующее «{subject}».',
      observationFound: 'Наблюдение — {title}: {observation}',
    },
  },
} satisfies MessageCatalogue;
export default messages;
