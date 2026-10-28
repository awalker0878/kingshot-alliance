import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Помощник',
    title: 'Спросите свой Альянс',
    eyebrow: 'Помощник Альянса · Авторизованные ответы',
    subtitle:
      'Спрашивайте о событиях, своём ростере и RSVP, своих назначениях, гайдах и наблюдениях Альянса, опубликованных планах территории, игровых данных с источниками и готовности к переносу в разрешённой области.',
    authorizationHint: 'Ответы используют только данные, которые вам разрешено видеть.',
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
      alliance_command: 'Общий обзор сотрудников',
      event_readiness: 'готовность к событию',
      bear_hunt_run: 'Медвежья охота',
      roster_freshness: 'губернатор наблюдение свежесть',
      transfer_verification: 'Проверка поставок',
      territory_comparison: 'Сравнение территорий',
    },
    sources: {
      event: 'Событие',
      roster: 'Состав',
      alliance_content: 'Руководство Альянса',
      observation: 'Наблюдение',
      game_fact: 'Данные игры',
      participation: 'Участие',
      battle_plan_assignment: 'Назначение боевого плана',
      transfer_assessment: 'Оценка переноса',
      territory_plan_revision: 'Опубликованный план территории',
      alliance_command: 'Общий обзор сотрудников',
      event_readiness: 'готовность к событию',
      bear_hunt_run: 'Медвежья охота',
      roster_freshness: 'губернатор наблюдение свежесть',
      transfer_verification: 'Проверка поставок',
      territory_comparison: 'Сравнение территорий',
      alliance_settings: 'настройки альянса',
      alliance_governance_history: 'история управления альянсом',
      alliance_roster_reconciliation: 'Согласование списка альянсов',
    },
    prompts: {
      swordland: 'Во сколько Swordland и есть ли я в составе?',
      nextEvent: 'Какое у меня следующее Событие?',
      bearGuide: 'Что говорит наше руководство Bear Hunt?',
      observation: 'Что мы наблюдали у противника?',
      heroFact: 'К какому поколению относится Amadeus?',
      rsvpWeek: 'На какие события я ответил на этой неделе?',
      battleAssignment: 'Какое у меня назначение в Swordland?',
      transferStatus: 'Чего мне не хватает для переноса?',
      territoryPlan: 'Какую схему улья мы используем для Bear Hunt?',
      allianceCommand: 'Что требует внимания сотрудника?',
      eventReadiness: 'Готово ли наше следующее проверенное мероприятие?',
      rallyGaps: 'Какие пробелы в ралли остаются до следующего события?',
      bearHuntHistory: 'Показать нашу последнюю историю исполнения Bear Hunt.',
      progressionFreshness: 'Какие замечания губернатора устарели или отсутствуют?',
      transferVerification: 'Какие участники Передачи нуждаются в проверке?',
      intelligenceChanges: 'Как изменился материальный Интеллект в последнее время?',
      territoryComparison:
        'Каким образом наблюдаемая территория соотносится с опубликованным планом?',
    },
    answers: {
      transferVerificationIncomplete:
        'В этом обзоре оценены {assessed} из {total} участников переноса. Как минимум {count} требуют внимания; {unassessed} здесь не оценены. Откройте готовность к переносу для актуальной проверки.',
      help: 'Я могу отвечать по разрешённым событиям, вашему ростеру и RSVP, вашим назначениям, гайдам и наблюдениям Альянса, опубликованным планам территории, игровым данным с источниками и статусу переноса в разрешённой области. Я не использую KingShot без источников.',
      unsupported:
        'Я могу отвечать только по поддерживаемым разрешённым источникам. Я не использую общие знания KingShot и не вношу изменения из Ассистента.',
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
      gameFactKnown:
        'Игровые данные — {title}. Значения с источниками ниже взяты из набора {datasetVersion}.',
      gameFactUnknown:
        'Игровые данные — для {title} нет поддерживаемого известного значения. Я не буду угадывать.',
      gameFactConflicting:
        'Игровые данные — поддерживаемые доказательства для {title} противоречат друг другу. Я не буду выбирать значение за вас.',
      participationNone: 'Для {event} нет сохранённого RSVP или регистрации от вас.',
      participationFound: 'Ваше участие в {event} показано ниже.',
      participationWeekNone: 'На этой неделе не найдено вашего RSVP или регистрации.',
      participationNotFound:
        'Не найдено подходящего состояния RSVP, регистрации или листа ожидания.',
      participationWeek: 'Найдено {count} записей вашего участия на этой неделе.',
      participationList: 'Найдено {count} подходящих записей участия.',
      battlePlanNotFound: 'Не найдено предстоящего разрешённого события с назначением для вас.',
      battlePlanNone: 'Сейчас у вас нет назначения боевого плана для {event}.',
      battlePlanOne: 'У вас одно назначение боевого плана для {event}.',
      battlePlanMany: 'У вас {count} назначений боевого плана для {event}.',
      transferNotInScope:
        'Я не могу предоставить готовность к переносу из вашей текущей разрешённой области.',
      transferStatus:
        'Ваша оценка переноса: {outcome}. Рассчитанные владельцем требования показаны ниже.',
      territoryPlanNotFound: 'К {event} не прикреплена опубликованная ревизия плана территории.',
      territoryPlanAmbiguous:
        'К {event} прикреплено несколько опубликованных ревизий плана территории; я не стану молча выбирать одну.',
      territoryPlanFound: '{event} использует {planName}, ревизию {revisionNumber}, для {purpose}.',
      rosterWriteHandoff:
        'Я не могу изменить ростер отсюда. Откройте обычный процесс {event}, чтобы внести изменение.',
      ownerEventWriteHandoff:
        'Я не могу сделать это изменение с помощью помощника. Откройте обычный рабочий процесс владельца {event}, чтобы продолжить.',
      ownerWriteHandoff:
        'Не удается изменить состояние {owner} из помощника. Ниже приведены текущие авторизованные факты; для продолжения откройте рабочий процесс владельца.',
      allianceCommandAttention:
        'В обзоре сотрудника имеется {count} фактический пункт (ы), требующий (ие) внимания. Ниже приводятся авторитетные источники.',
      allianceCommandAttentionNotAvailable:
        'Для этого альянса не существует официальных фактов обзора офицеров.',
      eventReadiness:
        'Следующее проверенное событие имеет {count} блокирующий элемент(ы) готовности. Проекция его владельца показана ниже.',
      eventReadinessNotAvailable:
        'Не удалось найти соответствующую проверенную готовность к событию в вашем обзоре уполномоченных должностных лиц.',
      rallyGaps:
        'Следующее проверенное событие имеет {count} разрыв(ы) готовности к сбору. Ниже приведены факты, принадлежащие Rally.',
      rallyGapsNotAvailable:
        'Я не смог найти факты готовности к ралли для соответствующего проверенного события.',
      bearHuntHistory:
        'Я нашел {count} недавних авторизованных записей прохождения охоты на медведей. Зафиксированные и принятые доказательства остаются явными ниже.',
      bearHuntHistoryNotAvailable:
        'Я не смог найти авторизованный завершенный запуск Bear Hunt с поддерживаемой историей.',
      progressionFreshness:
        '{count} Замечание(я) управляющего устарело(ы) или отсутствует(ют) в санкционированном прогнозе свежести списка.',
      progressionFreshnessNotAvailable:
        'свежесть наблюдений губернатора не доступна в вашем обзоре уполномоченного офицера.',
      transferVerification:
        '{count} Участник(и) передачи нуждаются в проверке или заблокированы в оценке владельца.',
      transferVerificationNotAvailable:
        'В настоящее время не существует санкционированного прогноза проверки передачи.',
      territoryComparison:
        'Утвержденное сопоставление территорий содержит {count} наблюдаемую разницу (различия). Отсутствующие и несовместимые замечания остаются четко ниже.',
      territoryComparisonNotAvailable:
        'Авторизованных опубликованных данных о сопоставлении территорий не имеется.',
      allianceSettings:
        'Текущие настройки альянса: {name}, URL-адрес {slug}, язык {language}, и часовой пояс {timezone}.',
      allianceGovernanceHistory:
        'Я нашел {count} недавних событий управления альянсом. Это факты аудита владельца только для чтения.',
      allianceRosterReconciliation:
        'В последнем принятом наблюдении в реестре имеется {needsReview} пункт(ы), требующий(ие) пересмотра, и {matched} пункт(ы), соответствующий(ие) нынешнему членскому составу.',
      allianceRosterReconciliationNotAvailable:
        'Пока не найдено никаких наблюдений в списке принятых альянсов для согласования.',
    },
    possibleMatches: 'Возможные совпадения',
    detailsHeading: 'Подробности ответа',
    requirementsHeading: 'Требования переноса',
    datasetVersion: 'Версия набора данных',
    evidenceStatus: 'Статус доказательств',
    checksum: 'Контрольная сумма',
    revisionLabel: 'Ревизия {revision}',
    participationSummary: 'RSVP: {response} · Регистрация: {registration}',
    waitlistPosition: 'Место в листе ожидания: {position}',
    teamLabel: 'Команда: {team}',
    handoffs: {
      openRoster: 'Открыть ростер события',
      openOwnerWorkflow: 'Открыть рабочий процесс владельца',
    },
    transferRequirements: {
      window_phase: 'Фаза окна переноса',
      transfer_group: 'Группа переноса',
      power_cap: 'Лимит силы',
      invitation: 'Приглашение',
      transfer_passes: 'Пропуска переноса',
      in_game_rules: 'Правила в игре',
    },
    transferStates: {
      met: 'Выполнено',
      unmet: 'Не выполнено',
      unknown: 'Неизвестно',
      stale: 'Устарело',
      conflicting: 'Противоречиво',
      not_applicable: 'Не применимо',
    },
    requirementValues: 'Фактически: {actual} · Требуется: {required}',
  },
  intelligenceChange: {
    title: 'Последние изменения разведданных',
    subtitle:
      'Изменения с указанием источников, полученные из разрешённой истории. Они описывают наблюдения, а не стратегические намерения.',
    empty: 'Для этой области нет существенных изменений разведданных.',
    source: 'Источник',
    observed: 'Наблюдение',
    baseline: 'База',
    current: 'Текущее',
    previous: 'Предыдущее',
    viewSource: 'Открыть источник',
    material: 'Существенное изменение',
    attention: 'Требует проверки',
  },
} satisfies MessageCatalogue;

export default messages;
