import type { MessageCatalogue } from '../../types';
const messages = {
  assistant: {
    navigation: 'المساعد',
    title: 'اسأل تحالفك',
    eyebrow: 'مساعد التحالف · إجابات مخوّلة',
    subtitle:
      'اسأل عن الفعاليات وقائمتك وأدلة التحالف والملاحظات. تستند الإجابات إلى مصادر لديك صلاحية عرضها بالفعل.',
    authorizationHint: 'تستخدم الإجابات فقط بيانات التحالف المسموح لك برؤيتها.',
    tryAsking: 'جرّب أن تسأل',
    conversation: 'محادثة مساعد التحالف',
    youAsked: 'سألت',
    possibleEvents: 'الفعاليات المحتملة',
    openEvent: 'فتح الفعالية',
    sourcesHeading: 'المصادر المستخدمة',
    sourceTime: 'وقت المصدر: {time}',
    questionLabel: 'اسأل تحالفك',
    questionPlaceholder: 'متى يبدأ Swordland وهل أنا ضمن القائمة؟',
    inputHint: '{count}/{max} حرفًا · Enter للسؤال · Shift+Enter لسطر جديد',
    asking: 'جارٍ التحقق من المصادر…',
    ask: 'اسأل',
    notRecorded: 'غير مسجل',
    classifications: {
      operational_fact: 'حقيقة تشغيلية',
      game_fact: 'بيانات اللعبة',
      alliance_strategy: 'استراتيجية التحالف',
      observation: 'ملاحظة',
    },
    sources: {
      event: 'فعالية',
      roster: 'القائمة',
      alliance_content: 'دليل التحالف',
      observation: 'ملاحظة',
      game_fact: 'بيانات اللعبة',
    },
    prompts: {
      swordland: 'متى يبدأ Swordland وهل أنا ضمن القائمة؟',
      nextEvent: 'ما هي فعاليتي التالية؟',
      bearGuide: 'ماذا يقول دليل Bear Hunt الخاص بنا؟',
      observation: 'ماذا لاحظنا عن خصمنا؟',
    },
    answers: {
      help: 'يمكنني الإجابة من الفعاليات وقائمتك وأدلة التحالف والملاحظات المصرح بها. لا أستخدم معرفة KingShot غير الموثقة بمصدر.',
      unsupported:
        'يمكنني الإجابة فقط من الفعاليات المصرح بها وقائمتك وأدلة التحالف والملاحظات. لا يمكنني إجراء تغييرات من هنا.',
      unavailable: 'يتعذر على مساعد التحالف التحقق من مصادره الآن. حاول مرة أخرى.',
      rateLimited: 'أنت تسأل بسرعة كبيرة. حاول بعد قليل.',
      validationError: 'أدخل سؤالًا بين حرفين و{max} حرفًا.',
      noUpcomingEvent: 'لم أجد فعالية قادمة مسموحًا لك بعرضها.',
      eventSubjectMissing: 'اذكر الفعالية التي تريد التحقق منها.',
      eventNotFound: 'لم أجد فعالية قادمة مخوّلة تطابق «{subject}».',
      eventAmbiguous: 'وجدت أكثر من فعالية تطابق «{subject}». افتح الفعالية المقصودة أدناه.',
      eventTime: 'تبدأ {event} في {startsAt}.',
      eventTimeNotRostered: 'تبدأ {event} في {startsAt}. أنت غير مدرج حاليًا في القائمة.',
      notRostered: 'أنت غير مدرج حاليًا في قائمة {event}.',
      eventTimeRostered:
        'تبدأ {event} في {startsAt}. أنت ضمن {roster}. الدور: {role}؛ الخانة: {slot}؛ الحالة: {status}.',
      rostered:
        'أنت ضمن قائمة {event} في {roster}. الدور: {role}؛ الخانة: {slot}؛ الحالة: {status}.',
      contentSubjectMissing: 'اذكر الفعالية أو الموضوع الذي تريد التحقق من دليل التحالف الخاص به.',
      contentNotFound: 'لم أجد محتوى تحالف منشورًا يطابق «{subject}».',
      contentFound: 'استراتيجية التحالف — {title}: {excerpt}',
      observationSubjectMissing: 'اذكر التحالف أو موضوع الملاحظة الذي تريد التحقق منه.',
      observationNotFound: 'لم أجد ملاحظة مخوّلة تطابق «{subject}».',
      observationFound: 'ملاحظة — {title}: {observation}',
    },
  },
} satisfies MessageCatalogue;
export default messages;
