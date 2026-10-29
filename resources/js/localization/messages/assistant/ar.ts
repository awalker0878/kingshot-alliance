import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'المساعد',
    title: 'اسأل تحالفك',
    eyebrow: 'مساعد التحالف · إجابات مخوّلة',
    subtitle:
      'اسأل عن الفعاليات وقائمتك وردود الحضور وتكليفاتك وأدلة التحالف وملاحظاته وخطط الأراضي المنشورة وبيانات اللعبة الموثقة واستعدادك للانتقال عندما تكون ضمن النطاق.',
    authorizationHint: 'تستخدم الإجابات فقط البيانات المسموح لك برؤيتها.',
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
      alliance_command: 'لمحة عامة عن الموظفين',
      event_readiness: 'التأهب للأحداث',
      bear_hunt_run: 'جري لدب القنص',
      roster_freshness: 'مدى حداثة الملاحظات',
      transfer_verification: 'التحقق من عمليات النقل',
      territory_comparison: 'مقارنة الأقاليم',
    },
    sources: {
      event: 'فعالية',
      roster: 'القائمة',
      alliance_content: 'دليل التحالف',
      observation: 'ملاحظة',
      game_fact: 'بيانات اللعبة',
      participation: 'المشاركة',
      battle_plan_assignment: 'تكليف خطة المعركة',
      transfer_assessment: 'تقييم الانتقال',
      territory_plan_revision: 'خطة أراضٍ منشورة',
      alliance_command: 'لمحة عامة عن الموظفين',
      event_readiness: 'التأهب للأحداث',
      bear_hunt_run: 'جري لدب القنص',
      roster_freshness: 'مدى حداثة الملاحظات',
      transfer_verification: 'التحقق من عمليات النقل',
      territory_comparison: 'مقارنة الأقاليم',
      alliance_settings: 'إعدادات التحالف',
      alliance_governance_history: 'تاريخ التحالف',
      alliance_roster_reconciliation: 'تسوية قائمة التحالفات',
    },
    prompts: {
      swordland: 'متى يبدأ Swordland وهل أنا ضمن القائمة؟',
      nextEvent: 'ما هي فعاليتي التالية؟',
      bearGuide: 'ماذا يقول دليل Bear Hunt الخاص بنا؟',
      observation: 'ماذا لاحظنا عن خصمنا؟',
      heroFact: 'ما جيل Amadeus؟',
      rsvpWeek: 'على أي فعاليات رددت هذا الأسبوع؟',
      battleAssignment: 'ما تكليفي في Swordland؟',
      transferStatus: 'ما الذي ينقصني للانتقال؟',
      territoryPlan: 'أي تخطيط للخلية نستخدم في Bear Hunt؟',
      allianceCommand: 'ما الذي يحتاج إلى انتباه الضابط؟',
      eventReadiness: 'هل حدثنا المقبل جاهز؟',
      rallyGaps: 'ما هي الثغرات المتبقية في السباق للحدث القادم؟',
      bearHuntHistory: 'أظهر تاريخ أداءنا الأخير في "صيد الدب"',
      progressionFreshness: 'أي ملاحظات من ملاحظات المحافظين قديمة أو مفقودة؟',
      transferVerification: 'أي المشاركين في عمليات النقل يحتاج إلى التحقق؟',
      intelligenceChanges: 'ماذا تغير في المخابرات مؤخراً؟',
      territoryComparison: 'كيف يقارن الإقليم المراقَب بالخطة المنشورة؟',
    },
    answers: {
      transferVerificationIncomplete:
        'قيّمت هذه النظرة العامة {assessed} من أصل {total} مشاركًا في النقل. يحتاج {count} منهم على الأقل إلى الانتباه؛ ولم يُقيّم {unassessed} هنا. افتح جاهزية النقل لإجراء الفحوص الحالية.',
      help: 'يمكنني الإجابة من الفعاليات المصرح بها وقائمتك وردودك وتكليفاتك وأدلة التحالف وملاحظاته وخطط الأراضي المنشورة وبيانات اللعبة الموثقة وحالة انتقالك ضمن النطاق. لا أستخدم معرفة KingShot غير موثقة.',
      unsupported:
        'يمكنني الإجابة فقط من المصادر المصرح بها والمدعومة. لا أستخدم معرفة KingShot العامة ولا أجري تغييرات من المساعد.',
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
      gameFactKnown:
        'بيانات اللعبة — {title}. القيم الموثقة أدناه من مجموعة البيانات {datasetVersion}.',
      gameFactUnknown: 'بيانات اللعبة — لا توجد لدي قيمة معروفة ومدعومة لـ {title}. لن أخمن.',
      gameFactConflicting:
        'بيانات اللعبة — الأدلة المحفوظة لـ {title} متعارضة. لن أختَر قيمة نيابةً عنك.',
      participationNone: 'لا يوجد رد حضور أو تسجيل محفوظ لك في {event}.',
      participationFound: 'مشاركتك في {event} موضحة أدناه.',
      participationWeekNone: 'لم أجد رد حضور أو تسجيلاً محفوظاً لك هذا الأسبوع.',
      participationNotFound: 'لم أجد حالة رد أو تسجيل أو انتظار مطابقة لك.',
      participationWeek: 'وجدت {count} سجل مشاركة لك هذا الأسبوع.',
      participationList: 'وجدت {count} سجل مشاركة مطابقاً لك.',
      battlePlanNotFound: 'لم أجد فعالية قادمة مصرحاً بها تحتوي على تكليف لك.',
      battlePlanNone: 'ليس لديك حالياً تكليف في خطة المعركة لـ {event}.',
      battlePlanOne: 'لديك تكليف واحد في خطة المعركة لـ {event}.',
      battlePlanMany: 'لديك {count} تكليفات في خطة المعركة لـ {event}.',
      transferNotInScope: 'لا يمكنني تقديم جاهزية الانتقال من نطاق الانتقال المصرح لك به حالياً.',
      transferStatus: 'تقييم انتقالك هو {outcome}. المتطلبات المحسوبة من المالك موضحة أدناه.',
      territoryPlanNotFound: 'لا توجد مراجعة منشورة لخطة أراضٍ مرتبطة بـ {event}.',
      territoryPlanAmbiguous:
        'ترتبط أكثر من مراجعة منشورة لخطة أراضٍ بـ {event}؛ لن أختَر واحدة بصمت.',
      territoryPlanFound:
        'تستخدم {event} الخطة {planName}، المراجعة {revisionNumber}، لغرض {purpose}.',
      rosterWriteHandoff:
        'لا يمكنني تغيير القائمة من هنا. افتح سير العمل العادي لـ {event} لإجراء التغيير.',
      ownerEventWriteHandoff:
        'لا أستطيع أن أقوم بهذا التغيير من المساعد. افتح تدفق العمل العادي للمالك {event} للاستمرار.',
      ownerWriteHandoff:
        'لا أستطيع تغيير الحالة {owner} من المساعد. وترد أدناه الحقائق المأذون بها حاليا؛ افتح تدفق عمل المالك للاستمرار.',
      allianceCommandAttention:
        'ويتضمن استعراض الموظف {count} بنداً وقائعياً يحتاج إلى اهتمام. وترد أدناه المصادر المأذون بها.',
      allianceCommandAttentionNotAvailable: 'لا توجد حقائق مأذون بها متاحة عن هذا التحالف.',
      eventReadiness:
        'الحدث التالي الذي تم التحقق منه له بند (بنود) جاهزية {count}. ويرد أدناه إسقاط صاحب المشروع.',
      eventReadinessNotAvailable:
        'لم أستطع العثور على استعداد متطابق للحدث في نظرتك العامة للموظف المأذون به.',
      rallyGaps:
        'الحدث التالي المتحقق منه لديه {count} فجوة (فجوات) جاهزية التجمع. وترد أدناه الحقائق التي يمتلكها التجمع.',
      rallyGapsNotAvailable:
        'لم أستطع العثور على حقائق الاستعداد للاجتماع لحدث متطابق تم التحقق منه.',
      bearHuntHistory:
        'لقد وجدت {count} سجل حديث معتمد من سجل (بير هانت) وتظل الحالات المسجلة والمقبولة للأدلة واضحة أدناه.',
      bearHuntHistoryNotAvailable: 'لم أستطع العثور على تسجيل معتمد من "صيد الدب" مع تاريخ مدعم',
      progressionFreshness:
        '{count} ملاحظة (ملاحظات) المحافظ قديمة أو مفقودة في التوقعات المأذون بها بشأن مدى حداثة القائمة.',
      progressionFreshnessNotAvailable:
        'حديثية ملاحظات الحاكم غير متاحة في نظرة عامة على الموظف المأذون به.',
      transferVerification:
        '{count} يحتاج المشارك (المشاركون) في النقل إلى التحقق أو أنهم معطلون في تقييم المالك.',
      transferVerificationNotAvailable: 'لا يوجد حاليا أي إسقاط مأذون به للتحقق من عمليات النقل.',
      territoryComparison:
        'وتتضمن المقارنة المأذون بها بين الأقاليم {count} فرقا (فروقا) لوحظت. وترد أدناه الملاحظات المفقودة والمتضاربة.',
      territoryComparisonNotAvailable: 'ولا توجد مقارنة منشورة مأذون بها بين الأقاليم.',
      allianceSettings:
        'الإعدادات الحالية للتحالف هي {name}، اسم URL {slug}، اللغة {language}، ومنطقة التوقيت {timezone}.',
      allianceGovernanceHistory:
        'وجدت {count} حدث (ات) حديثة مأذون بها لإدارة التحالف. هذه هي حقائق مراجعة حسابات المالك القراءة فقط.',
      allianceRosterReconciliation:
        'وتتضمن آخر ملاحظة مقبولة في القائمة {needsReview} بنداً (بنود) يلزم استعراضها و{matched} يطابق العضوية الحالية.',
      allianceRosterReconciliationNotAvailable:
        'ولا توجد ملاحظة مقبولة في قائمة التحالفات متاحة للتوفيق بينها حتى الآن.',
    },
    possibleMatches: 'تطابقات محتملة',
    detailsHeading: 'تفاصيل الإجابة',
    requirementsHeading: 'متطلبات الانتقال',
    datasetVersion: 'إصدار مجموعة البيانات',
    evidenceStatus: 'حالة الدليل',
    checksum: 'المجموع الاختباري',
    revisionLabel: 'المراجعة {revision}',
    participationSummary: 'الرد: {response} · التسجيل: {registration}',
    waitlistPosition: 'موضع قائمة الانتظار: {position}',
    teamLabel: 'الفريق: {team}',
    handoffs: {
      openRoster: 'فتح قائمة الفعالية',
      openOwnerWorkflow: 'سير العمل المفتوح للمالك',
    },
    transferRequirements: {
      window_phase: 'مرحلة نافذة الانتقال',
      transfer_group: 'مجموعة الانتقال',
      power_cap: 'حد القوة',
      invitation: 'الدعوة',
      transfer_passes: 'تصاريح الانتقال',
      in_game_rules: 'قواعد داخل اللعبة',
    },
    transferStates: {
      met: 'مستوفى',
      unmet: 'غير مستوفى',
      unknown: 'غير معروف',
      stale: 'قديم',
      conflicting: 'متعارض',
      not_applicable: 'غير منطبق',
    },
    requirementValues: 'الفعلي: {actual} · المطلوب: {required}',
  },
  intelligenceChange: {
    title: 'أحدث تغييرات الاستخبارات',
    subtitle:
      'تغييرات موثقة بالمصادر مشتقة من السجل المصرح به. تصف التغييرات الملاحظات ولا تستنتج النية الاستراتيجية.',
    empty: 'لا توجد تغييرات استخباراتية جوهرية متاحة لهذا النطاق.',
    source: 'المصدر',
    observed: 'لوحظ',
    baseline: 'خط الأساس',
    current: 'الحالي',
    previous: 'السابق',
    viewSource: 'عرض المصدر',
    material: 'تغيير جوهري',
    attention: 'يحتاج إلى تحقق',
  },
} satisfies MessageCatalogue;

export default messages;
