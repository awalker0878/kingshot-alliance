import type { LocaleCode } from './locales';
import type { MessageCatalogue } from './types';

type NonEnglishLocale = Exclude<LocaleCode, 'en'>;

type AssistantExtensionStrings = {
  subtitle: string;
  authorizationHint: string;
  possibleMatches: string;
  detailsHeading: string;
  requirementsHeading: string;
  datasetVersion: string;
  evidenceStatus: string;
  checksum: string;
  revisionLabel: string;
  participationSummary: string;
  waitlistPosition: string;
  teamLabel: string;
  sources: {
    participation: string;
    battle_plan_assignment: string;
    transfer_assessment: string;
    territory_plan_revision: string;
  };
  prompts: {
    heroFact: string;
    rsvpWeek: string;
    battleAssignment: string;
    transferStatus: string;
    territoryPlan: string;
  };
  handoffs: { openRoster: string };
  answers: {
    help: string;
    unsupported: string;
    gameFactKnown: string;
    gameFactUnknown: string;
    gameFactConflicting: string;
    participationNone: string;
    participationFound: string;
    participationWeekNone: string;
    participationNotFound: string;
    participationWeek: string;
    participationList: string;
    battlePlanNotFound: string;
    battlePlanNone: string;
    battlePlanOne: string;
    battlePlanMany: string;
    transferNotInScope: string;
    transferStatus: string;
    territoryPlanNotFound: string;
    territoryPlanAmbiguous: string;
    territoryPlanFound: string;
    rosterWriteHandoff: string;
  };
};

const extensions = {
  ar: {
    subtitle:
      'اسأل عن الفعاليات وقائمتك وردود الحضور وتكليفاتك وأدلة التحالف وملاحظاته وخطط الأراضي المنشورة وبيانات اللعبة الموثقة واستعدادك للانتقال عندما تكون ضمن النطاق.',
    authorizationHint: 'تستخدم الإجابات فقط البيانات المسموح لك برؤيتها.',
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
    sources: {
      participation: 'المشاركة',
      battle_plan_assignment: 'تكليف خطة المعركة',
      transfer_assessment: 'تقييم الانتقال',
      territory_plan_revision: 'خطة أراضٍ منشورة',
    },
    prompts: {
      heroFact: 'ما جيل Amadeus؟',
      rsvpWeek: 'على أي فعاليات رددت هذا الأسبوع؟',
      battleAssignment: 'ما تكليفي في Swordland؟',
      transferStatus: 'ما الذي ينقصني للانتقال؟',
      territoryPlan: 'أي تخطيط للخلية نستخدم في Bear Hunt؟',
    },
    handoffs: { openRoster: 'فتح قائمة الفعالية' },
    answers: {
      help: 'يمكنني الإجابة من الفعاليات المصرح بها وقائمتك وردودك وتكليفاتك وأدلة التحالف وملاحظاته وخطط الأراضي المنشورة وبيانات اللعبة الموثقة وحالة انتقالك ضمن النطاق. لا أستخدم معرفة KingShot غير موثقة.',
      unsupported:
        'يمكنني الإجابة فقط من المصادر المصرح بها والمدعومة. لا أستخدم معرفة KingShot العامة ولا أجري تغييرات من المساعد.',
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
    },
  },
  de: {
    subtitle:
      'Frage nach Events, deinem Roster und deinen Zusagen, deinen Aufgaben, Allianz-Guides und Beobachtungen, veröffentlichten Gebietsplänen, belegten Spieldaten und deiner Transferbereitschaft, wenn du im gültigen Bereich bist.',
    authorizationHint: 'Antworten verwenden nur Daten, die du sehen darfst.',
    possibleMatches: 'Mögliche Treffer',
    detailsHeading: 'Antwortdetails',
    requirementsHeading: 'Transferanforderungen',
    datasetVersion: 'Datensatzversion',
    evidenceStatus: 'Nachweisstatus',
    checksum: 'Prüfsumme',
    revisionLabel: 'Revision {revision}',
    participationSummary: 'Zusage: {response} · Registrierung: {registration}',
    waitlistPosition: 'Wartelistenplatz: {position}',
    teamLabel: 'Team: {team}',
    sources: {
      participation: 'Teilnahme',
      battle_plan_assignment: 'Gefechtsplan-Aufgabe',
      transfer_assessment: 'Transferbewertung',
      territory_plan_revision: 'Veröffentlichter Gebietsplan',
    },
    prompts: {
      heroFact: 'Welche Generation ist Amadeus?',
      rsvpWeek: 'Wofür habe ich diese Woche zugesagt?',
      battleAssignment: 'Was ist meine Swordland-Aufgabe?',
      transferStatus: 'Was fehlt mir für den Transfer?',
      territoryPlan: 'Welches Hive-Layout nutzen wir für Bear Hunt?',
    },
    handoffs: { openRoster: 'Event-Roster öffnen' },
    answers: {
      help: 'Ich kann aus autorisierten Events, deinem Roster und deinen Zusagen, deinen Aufgaben, Allianz-Guides und Beobachtungen, veröffentlichten Gebietsplänen, belegten Spieldaten und deinem Transferstatus im gültigen Bereich antworten. Ich nutze kein unbelegtes KingShot-Wissen.',
      unsupported:
        'Ich kann nur aus unterstützten autorisierten Quellen antworten. Ich nutze kein allgemeines KingShot-Wissen und ändere hier nichts.',
      gameFactKnown:
        'Spieldaten — {title}. Die belegten Werte unten stammen aus Datensatz {datasetVersion}.',
      gameFactUnknown:
        'Spieldaten — für {title} liegt kein unterstützter bekannter Wert vor. Ich rate nicht.',
      gameFactConflicting:
        'Spieldaten — die gepflegten Nachweise für {title} widersprechen sich. Ich wähle keinen Wert für dich aus.',
      participationNone: 'Für {event} ist keine Zusage oder Registrierung von dir erfasst.',
      participationFound: 'Deine Teilnahme für {event} steht unten.',
      participationWeekNone: 'Diese Woche wurde keine Zusage oder Registrierung von dir gefunden.',
      participationNotFound:
        'Ich habe keinen passenden Zusage-, Registrierungs- oder Wartelistenstatus von dir gefunden.',
      participationWeek:
        'Ich habe {count} Teilnahme-Eintrag/-Einträge von dir für diese Woche gefunden.',
      participationList: 'Ich habe {count} passende Teilnahme-Einträge von dir gefunden.',
      battlePlanNotFound:
        'Ich konnte kein kommendes autorisiertes Event mit einer Aufgabe für dich finden.',
      battlePlanNone: 'Du hast derzeit keine Gefechtsplan-Aufgabe für {event}.',
      battlePlanOne: 'Du hast eine Gefechtsplan-Aufgabe für {event}.',
      battlePlanMany: 'Du hast {count} Gefechtsplan-Aufgaben für {event}.',
      transferNotInScope:
        'Aus deinem aktuellen autorisierten Transferbereich kann ich keine Transferbereitschaft liefern.',
      transferStatus:
        'Deine Transferbewertung lautet {outcome}. Die vom Eigentümer berechneten Anforderungen stehen unten.',
      territoryPlanNotFound: 'An {event} ist keine veröffentlichte Gebietsplan-Revision angehängt.',
      territoryPlanAmbiguous:
        'An {event} sind mehrere veröffentlichte Gebietsplan-Revisionen angehängt; ich wähle nicht stillschweigend eine aus.',
      territoryPlanFound: '{event} verwendet {planName}, Revision {revisionNumber}, für {purpose}.',
      rosterWriteHandoff:
        'Ich kann das Roster hier nicht ändern. Öffne den normalen Ablauf für {event}, um die Änderung vorzunehmen.',
    },
  },
  es: {
    subtitle:
      'Pregunta por eventos, tu roster y confirmaciones, tus asignaciones, guías y observaciones de la Alianza, planes de territorio publicados, datos del juego con fuentes y tu preparación para transferirte cuando estés dentro del ámbito.',
    authorizationHint: 'Las respuestas usan solo datos que tienes autorización para ver.',
    possibleMatches: 'Coincidencias posibles',
    detailsHeading: 'Detalles de la respuesta',
    requirementsHeading: 'Requisitos de transferencia',
    datasetVersion: 'Versión del conjunto de datos',
    evidenceStatus: 'Estado de la evidencia',
    checksum: 'Suma de comprobación',
    revisionLabel: 'Revisión {revision}',
    participationSummary: 'Confirmación: {response} · Registro: {registration}',
    waitlistPosition: 'Posición en espera: {position}',
    teamLabel: 'Equipo: {team}',
    sources: {
      participation: 'Participación',
      battle_plan_assignment: 'Asignación del plan de batalla',
      transfer_assessment: 'Evaluación de transferencia',
      territory_plan_revision: 'Plan de territorio publicado',
    },
    prompts: {
      heroFact: '¿De qué generación es Amadeus?',
      rsvpWeek: '¿A qué confirmé asistencia esta semana?',
      battleAssignment: '¿Cuál es mi asignación de Swordland?',
      transferStatus: '¿Qué me falta para transferirme?',
      territoryPlan: '¿Qué diseño de colmena usamos para Bear Hunt?',
    },
    handoffs: { openRoster: 'Abrir roster del evento' },
    answers: {
      help: 'Puedo responder desde eventos autorizados, tu roster y confirmaciones, tus asignaciones, guías y observaciones de la Alianza, planes de territorio publicados, datos del juego con fuentes y tu estado de transferencia dentro del ámbito. No uso conocimiento de KingShot sin fuentes.',
      unsupported:
        'Solo puedo responder desde fuentes autorizadas compatibles. No uso conocimiento general de KingShot ni hago cambios desde el Asistente.',
      gameFactKnown:
        'Datos del juego — {title}. Los valores con fuente se muestran abajo desde el conjunto {datasetVersion}.',
      gameFactUnknown:
        'Datos del juego — no tengo un valor conocido compatible para {title}. No voy a adivinar.',
      gameFactConflicting:
        'Datos del juego — la evidencia mantenida para {title} es contradictoria. No elegiré un valor por ti.',
      participationNone: 'No tienes confirmación ni registro guardado para {event}.',
      participationFound: 'Tu participación en {event} aparece abajo.',
      participationWeekNone: 'No encontré confirmaciones ni registros tuyos esta semana.',
      participationNotFound:
        'No encontré un estado coincidente de confirmación, registro o lista de espera para ti.',
      participationWeek: 'Encontré {count} registro(s) de participación tuyos esta semana.',
      participationList: 'Encontré {count} registro(s) de participación coincidentes.',
      battlePlanNotFound: 'No encontré un próximo evento autorizado con una asignación para ti.',
      battlePlanNone: 'Actualmente no tienes una asignación de batalla para {event}.',
      battlePlanOne: 'Tienes una asignación de batalla para {event}.',
      battlePlanMany: 'Tienes {count} asignaciones de batalla para {event}.',
      transferNotInScope:
        'No puedo ofrecer preparación de transferencia desde tu ámbito autorizado actual.',
      transferStatus:
        'Tu evaluación de transferencia es {outcome}. Los requisitos calculados por el propietario aparecen abajo.',
      territoryPlanNotFound: 'No hay una revisión publicada de territorio adjunta a {event}.',
      territoryPlanAmbiguous:
        'Hay varias revisiones publicadas de territorio adjuntas a {event}; no elegiré una en silencio.',
      territoryPlanFound: '{event} usa {planName}, revisión {revisionNumber}, para {purpose}.',
      rosterWriteHandoff:
        'No puedo cambiar el roster desde aquí. Abre el flujo normal de {event} para hacer el cambio.',
    },
  },
  fr: {
    subtitle:
      'Demandez des informations sur les événements, votre roster et vos réponses, vos affectations, les guides et observations de l’Alliance, les plans de territoire publiés, les données de jeu sourcées et votre préparation au transfert lorsque vous êtes dans le périmètre.',
    authorizationHint:
      'Les réponses utilisent uniquement les données que vous êtes autorisé à consulter.',
    possibleMatches: 'Correspondances possibles',
    detailsHeading: 'Détails de la réponse',
    requirementsHeading: 'Conditions de transfert',
    datasetVersion: 'Version du jeu de données',
    evidenceStatus: 'État de la preuve',
    checksum: 'Somme de contrôle',
    revisionLabel: 'Révision {revision}',
    participationSummary: 'Réponse : {response} · Inscription : {registration}',
    waitlistPosition: 'Position en liste d’attente : {position}',
    teamLabel: 'Équipe : {team}',
    sources: {
      participation: 'Participation',
      battle_plan_assignment: 'Affectation du plan de bataille',
      transfer_assessment: 'Évaluation du transfert',
      territory_plan_revision: 'Plan de territoire publié',
    },
    prompts: {
      heroFact: 'De quelle génération est Amadeus ?',
      rsvpWeek: 'À quoi ai-je répondu cette semaine ?',
      battleAssignment: 'Quelle est mon affectation Swordland ?',
      transferStatus: 'Que me manque-t-il pour le transfert ?',
      territoryPlan: 'Quel plan de ruche utilisons-nous pour Bear Hunt ?',
    },
    handoffs: { openRoster: 'Ouvrir le roster de l’événement' },
    answers: {
      help: 'Je peux répondre à partir des événements autorisés, de votre roster et de vos réponses, de vos affectations, des guides et observations de l’Alliance, des plans de territoire publiés, des données de jeu sourcées et de votre statut de transfert dans le périmètre. Je n’utilise pas de connaissances KingShot non sourcées.',
      unsupported:
        'Je peux répondre uniquement à partir des sources autorisées prises en charge. Je n’utilise pas de connaissances générales KingShot et je n’effectue aucune modification depuis l’Assistant.',
      gameFactKnown:
        'Données de jeu — {title}. Les valeurs sourcées ci-dessous proviennent du jeu de données {datasetVersion}.',
      gameFactUnknown:
        'Données de jeu — je ne dispose d’aucune valeur connue prise en charge pour {title}. Je ne vais pas deviner.',
      gameFactConflicting:
        'Données de jeu — les preuves maintenues pour {title} sont contradictoires. Je ne choisirai pas une valeur à votre place.',
      participationNone: 'Aucune réponse ou inscription n’est enregistrée pour vous sur {event}.',
      participationFound: 'Votre participation à {event} est affichée ci-dessous.',
      participationWeekNone:
        'Je n’ai trouvé aucune réponse ou inscription enregistrée pour vous cette semaine.',
      participationNotFound:
        'Je n’ai trouvé aucun état correspondant de réponse, d’inscription ou de liste d’attente.',
      participationWeek:
        'J’ai trouvé {count} enregistrement(s) de participation pour vous cette semaine.',
      participationList: 'J’ai trouvé {count} enregistrement(s) de participation correspondant(s).',
      battlePlanNotFound:
        'Je n’ai trouvé aucun événement autorisé à venir avec une affectation pour vous.',
      battlePlanNone:
        'Vous n’avez actuellement aucune affectation de plan de bataille pour {event}.',
      battlePlanOne: 'Vous avez une affectation de plan de bataille pour {event}.',
      battlePlanMany: 'Vous avez {count} affectations de plan de bataille pour {event}.',
      transferNotInScope:
        'Je ne peux pas fournir votre préparation au transfert depuis votre périmètre de transfert autorisé actuel.',
      transferStatus:
        'Votre évaluation de transfert est {outcome}. Les conditions calculées par le propriétaire sont affichées ci-dessous.',
      territoryPlanNotFound: 'Aucune révision publiée de plan de territoire n’est liée à {event}.',
      territoryPlanAmbiguous:
        'Plusieurs révisions publiées de plan de territoire sont liées à {event} ; je n’en choisirai pas une silencieusement.',
      territoryPlanFound: '{event} utilise {planName}, révision {revisionNumber}, pour {purpose}.',
      rosterWriteHandoff:
        'Je ne peux pas modifier le roster ici. Ouvrez le flux normal de {event} pour effectuer la modification.',
    },
  },
  id: {
    subtitle:
      'Tanyakan Event, roster dan RSVP Anda, tugas Anda, panduan dan observasi Alliance, rencana wilayah yang diterbitkan, data game bersumber, serta kesiapan transfer Anda saat berada dalam cakupan.',
    authorizationHint: 'Jawaban hanya menggunakan data yang boleh Anda lihat.',
    possibleMatches: 'Kemungkinan kecocokan',
    detailsHeading: 'Detail jawaban',
    requirementsHeading: 'Persyaratan transfer',
    datasetVersion: 'Versi dataset',
    evidenceStatus: 'Status bukti',
    checksum: 'Checksum',
    revisionLabel: 'Revisi {revision}',
    participationSummary: 'RSVP: {response} · Registrasi: {registration}',
    waitlistPosition: 'Posisi daftar tunggu: {position}',
    teamLabel: 'Tim: {team}',
    sources: {
      participation: 'Partisipasi',
      battle_plan_assignment: 'Tugas rencana pertempuran',
      transfer_assessment: 'Penilaian transfer',
      territory_plan_revision: 'Rencana wilayah terbit',
    },
    prompts: {
      heroFact: 'Amadeus generasi berapa?',
      rsvpWeek: 'Event apa yang saya RSVP minggu ini?',
      battleAssignment: 'Apa tugas Swordland saya?',
      transferStatus: 'Apa yang masih kurang untuk transfer?',
      territoryPlan: 'Layout hive mana yang kita gunakan untuk Bear Hunt?',
    },
    handoffs: { openRoster: 'Buka roster Event' },
    answers: {
      help: 'Saya dapat menjawab dari Event yang diizinkan, roster dan RSVP Anda, tugas Anda, panduan dan observasi Alliance, rencana wilayah terbit, data game bersumber, dan status transfer Anda dalam cakupan. Saya tidak memakai pengetahuan KingShot tanpa sumber.',
      unsupported:
        'Saya hanya dapat menjawab dari sumber resmi yang didukung. Saya tidak memakai pengetahuan KingShot umum atau membuat perubahan dari Assistant.',
      gameFactKnown:
        'Data game — {title}. Nilai bersumber di bawah berasal dari dataset {datasetVersion}.',
      gameFactUnknown:
        'Data game — saya tidak memiliki nilai dikenal yang didukung untuk {title}. Saya tidak akan menebak.',
      gameFactConflicting:
        'Data game — bukti yang dipelihara untuk {title} saling bertentangan. Saya tidak akan memilih nilai untuk Anda.',
      participationNone: 'Tidak ada RSVP atau registrasi Anda yang tercatat untuk {event}.',
      participationFound: 'Partisipasi Anda untuk {event} ditampilkan di bawah.',
      participationWeekNone: 'Tidak ada RSVP atau registrasi Anda yang tercatat minggu ini.',
      participationNotFound:
        'Tidak ditemukan status RSVP, registrasi, atau daftar tunggu yang cocok untuk Anda.',
      participationWeek: 'Ditemukan {count} catatan partisipasi Anda minggu ini.',
      participationList: 'Ditemukan {count} catatan partisipasi yang cocok untuk Anda.',
      battlePlanNotFound: 'Tidak ditemukan Event mendatang yang diizinkan dengan tugas untuk Anda.',
      battlePlanNone: 'Saat ini Anda tidak memiliki tugas rencana pertempuran untuk {event}.',
      battlePlanOne: 'Anda memiliki satu tugas rencana pertempuran untuk {event}.',
      battlePlanMany: 'Anda memiliki {count} tugas rencana pertempuran untuk {event}.',
      transferNotInScope:
        'Saya tidak dapat memberikan kesiapan transfer dari cakupan transfer Anda saat ini.',
      transferStatus:
        'Penilaian transfer Anda adalah {outcome}. Persyaratan yang dihitung pemilik ditampilkan di bawah.',
      territoryPlanNotFound: 'Tidak ada revisi rencana wilayah terbit yang terpasang pada {event}.',
      territoryPlanAmbiguous:
        'Ada beberapa revisi rencana wilayah terbit pada {event}; saya tidak akan memilih diam-diam.',
      territoryPlanFound:
        '{event} menggunakan {planName}, revisi {revisionNumber}, untuk {purpose}.',
      rosterWriteHandoff:
        'Saya tidak dapat mengubah roster dari sini. Buka alur normal {event} untuk melakukan perubahan.',
    },
  },
  it: {
    subtitle:
      'Chiedi di Event, del tuo roster e delle tue RSVP, dei tuoi incarichi, delle guide e osservazioni dell’Alleanza, dei piani territorio pubblicati, dei dati di gioco con fonte e della tua prontezza al trasferimento quando sei nell’ambito.',
    authorizationHint: 'Le risposte usano solo dati che sei autorizzato a vedere.',
    possibleMatches: 'Possibili corrispondenze',
    detailsHeading: 'Dettagli risposta',
    requirementsHeading: 'Requisiti di trasferimento',
    datasetVersion: 'Versione dataset',
    evidenceStatus: 'Stato evidenza',
    checksum: 'Checksum',
    revisionLabel: 'Revisione {revision}',
    participationSummary: 'RSVP: {response} · Registrazione: {registration}',
    waitlistPosition: 'Posizione in lista d’attesa: {position}',
    teamLabel: 'Squadra: {team}',
    sources: {
      participation: 'Partecipazione',
      battle_plan_assignment: 'Incarico piano di battaglia',
      transfer_assessment: 'Valutazione trasferimento',
      territory_plan_revision: 'Piano territorio pubblicato',
    },
    prompts: {
      heroFact: 'Di quale generazione è Amadeus?',
      rsvpWeek: 'A cosa ho risposto questa settimana?',
      battleAssignment: 'Qual è il mio incarico Swordland?',
      transferStatus: 'Cosa mi manca per il trasferimento?',
      territoryPlan: 'Quale layout dell’hive usiamo per Bear Hunt?',
    },
    handoffs: { openRoster: 'Apri roster Event' },
    answers: {
      help: 'Posso rispondere da Event autorizzati, dal tuo roster e RSVP, dai tuoi incarichi, dalle guide e osservazioni dell’Alleanza, dai piani territorio pubblicati, dai dati di gioco con fonte e dal tuo stato di trasferimento nell’ambito. Non uso conoscenze KingShot senza fonte.',
      unsupported:
        'Posso rispondere solo da fonti autorizzate supportate. Non uso conoscenze generiche di KingShot e non effettuo modifiche dall’Assistente.',
      gameFactKnown:
        'Dati di gioco — {title}. I valori con fonte sotto provengono dal dataset {datasetVersion}.',
      gameFactUnknown:
        'Dati di gioco — non ho un valore noto supportato per {title}. Non farò ipotesi.',
      gameFactConflicting:
        'Dati di gioco — le evidenze mantenute per {title} sono in conflitto. Non sceglierò un valore per te.',
      participationNone: 'Non hai RSVP o registrazioni registrate per {event}.',
      participationFound: 'La tua partecipazione a {event} è mostrata sotto.',
      participationWeekNone: 'Non ho trovato RSVP o registrazioni tue questa settimana.',
      participationNotFound:
        'Non ho trovato uno stato RSVP, registrazione o lista d’attesa corrispondente.',
      participationWeek:
        'Ho trovato {count} registrazioni di partecipazione per te questa settimana.',
      participationList: 'Ho trovato {count} registrazioni di partecipazione corrispondenti.',
      battlePlanNotFound: 'Non ho trovato un prossimo Event autorizzato con un incarico per te.',
      battlePlanNone: 'Al momento non hai un incarico di battaglia per {event}.',
      battlePlanOne: 'Hai un incarico di battaglia per {event}.',
      battlePlanMany: 'Hai {count} incarichi di battaglia per {event}.',
      transferNotInScope:
        'Non posso fornire la prontezza al trasferimento dal tuo attuale ambito autorizzato.',
      transferStatus:
        'La tua valutazione di trasferimento è {outcome}. I requisiti calcolati dal proprietario sono mostrati sotto.',
      territoryPlanNotFound:
        'Nessuna revisione pubblicata del piano territorio è collegata a {event}.',
      territoryPlanAmbiguous:
        'Più revisioni pubblicate del piano territorio sono collegate a {event}; non ne sceglierò una in silenzio.',
      territoryPlanFound: '{event} usa {planName}, revisione {revisionNumber}, per {purpose}.',
      rosterWriteHandoff:
        'Non posso modificare il roster da qui. Apri il normale flusso di {event} per effettuare la modifica.',
    },
  },
  ja: {
    subtitle:
      'イベント、自分のロスターとRSVP、自分の割り当て、同盟ガイドと観測、公開済み領土プラン、出典付きゲームデータ、対象範囲内の移転準備状況について質問できます。',
    authorizationHint: '回答には、あなたが閲覧を許可されているデータだけを使用します。',
    possibleMatches: '候補',
    detailsHeading: '回答の詳細',
    requirementsHeading: '移転要件',
    datasetVersion: 'データセット版',
    evidenceStatus: '証拠ステータス',
    checksum: 'チェックサム',
    revisionLabel: 'リビジョン {revision}',
    participationSummary: 'RSVP: {response} · 登録: {registration}',
    waitlistPosition: '待機リスト順位: {position}',
    teamLabel: 'チーム: {team}',
    sources: {
      participation: '参加状況',
      battle_plan_assignment: '戦闘計画の割り当て',
      transfer_assessment: '移転評価',
      territory_plan_revision: '公開済み領土プラン',
    },
    prompts: {
      heroFact: 'Amadeus は第何世代？',
      rsvpWeek: '今週RSVPしたイベントは？',
      battleAssignment: 'Swordlandでの自分の割り当ては？',
      transferStatus: '移転に足りないものは？',
      territoryPlan: 'Bear Huntではどのハイブ配置を使う？',
    },
    handoffs: { openRoster: 'イベントロスターを開く' },
    answers: {
      help: '許可されたイベント、自分のロスターとRSVP、自分の割り当て、同盟ガイドと観測、公開済み領土プラン、出典付きゲームデータ、対象範囲内の移転状況から回答できます。出典のないKingShot知識は使いません。',
      unsupported:
        '対応する許可済みソースからのみ回答できます。一般的なKingShot知識は使わず、Assistantから変更もしません。',
      gameFactKnown:
        'ゲームデータ — {title}。下の出典付き値はデータセット {datasetVersion} から取得しています。',
      gameFactUnknown:
        'ゲームデータ — {title} について対応する既知の値がありません。推測はしません。',
      gameFactConflicting:
        'ゲームデータ — {title} の維持された証拠が競合しています。こちらで値を選びません。',
      participationNone: '{event} にあなたのRSVPまたは登録記録はありません。',
      participationFound: '{event} の参加状況を下に表示します。',
      participationWeekNone: '今週のあなたのRSVPまたは登録記録は見つかりませんでした。',
      participationNotFound: '一致するRSVP、登録、待機リスト状態は見つかりませんでした。',
      participationWeek: '今週の参加記録が {count} 件見つかりました。',
      participationList: '一致する参加記録が {count} 件見つかりました。',
      battlePlanNotFound:
        'あなたへの割り当てがある、閲覧可能な今後のイベントは見つかりませんでした。',
      battlePlanNone: '現在、{event} の戦闘計画割り当てはありません。',
      battlePlanOne: '{event} の戦闘計画割り当てが1件あります。',
      battlePlanMany: '{event} の戦闘計画割り当てが {count} 件あります。',
      transferNotInScope: '現在の許可された移転範囲から移転準備状況を提供できません。',
      transferStatus: '移転評価は {outcome} です。所有機能が計算した要件を下に表示します。',
      territoryPlanNotFound: '{event} に公開済み領土プランのリビジョンは添付されていません。',
      territoryPlanAmbiguous:
        '{event} に複数の公開済み領土プランが添付されています。勝手には選びません。',
      territoryPlanFound:
        '{event} では {planName} のリビジョン {revisionNumber} を {purpose} 用に使用しています。',
      rosterWriteHandoff:
        'ここからロスターは変更できません。通常の {event} ワークフローを開いて変更してください。',
    },
  },
  ko: {
    subtitle:
      '이벤트, 내 로스터와 RSVP, 내 배정, 얼라이언스 가이드와 관측, 게시된 영토 계획, 출처가 있는 게임 데이터, 범위 내 이전 준비 상태를 질문할 수 있습니다.',
    authorizationHint: '답변에는 내가 볼 권한이 있는 데이터만 사용됩니다.',
    possibleMatches: '가능한 일치 항목',
    detailsHeading: '답변 세부 정보',
    requirementsHeading: '이전 요구 사항',
    datasetVersion: '데이터셋 버전',
    evidenceStatus: '증거 상태',
    checksum: '체크섬',
    revisionLabel: '리비전 {revision}',
    participationSummary: 'RSVP: {response} · 등록: {registration}',
    waitlistPosition: '대기 목록 순위: {position}',
    teamLabel: '팀: {team}',
    sources: {
      participation: '참여',
      battle_plan_assignment: '전투 계획 배정',
      transfer_assessment: '이전 평가',
      territory_plan_revision: '게시된 영토 계획',
    },
    prompts: {
      heroFact: 'Amadeus는 몇 세대인가요?',
      rsvpWeek: '이번 주에 어떤 이벤트에 RSVP했나요?',
      battleAssignment: '내 Swordland 배정은 무엇인가요?',
      transferStatus: '이전에 무엇이 부족한가요?',
      territoryPlan: 'Bear Hunt에 어떤 하이브 배치를 사용하나요?',
    },
    handoffs: { openRoster: '이벤트 로스터 열기' },
    answers: {
      help: '권한이 있는 이벤트, 내 로스터와 RSVP, 내 배정, 얼라이언스 가이드와 관측, 게시된 영토 계획, 출처가 있는 게임 데이터, 범위 내 이전 상태에서 답할 수 있습니다. 출처 없는 KingShot 지식은 사용하지 않습니다.',
      unsupported:
        '지원되는 권한 있는 출처에서만 답할 수 있습니다. 일반 KingShot 지식은 사용하지 않으며 Assistant에서 변경하지 않습니다.',
      gameFactKnown:
        '게임 데이터 — {title}. 아래 출처 기반 값은 데이터셋 {datasetVersion}에서 가져왔습니다.',
      gameFactUnknown:
        '게임 데이터 — {title}에 대해 지원되는 알려진 값이 없습니다. 추측하지 않습니다.',
      gameFactConflicting:
        '게임 데이터 — {title}에 대한 유지된 증거가 서로 충돌합니다. 임의로 값을 선택하지 않습니다.',
      participationNone: '{event}에 기록된 RSVP 또는 등록이 없습니다.',
      participationFound: '{event} 참여 상태가 아래에 표시됩니다.',
      participationWeekNone: '이번 주에 기록된 RSVP 또는 등록이 없습니다.',
      participationNotFound: '일치하는 RSVP, 등록 또는 대기 목록 상태를 찾지 못했습니다.',
      participationWeek: '이번 주 참여 기록 {count}건을 찾았습니다.',
      participationList: '일치하는 참여 기록 {count}건을 찾았습니다.',
      battlePlanNotFound: '내 배정이 있는 권한 있는 예정 이벤트를 찾지 못했습니다.',
      battlePlanNone: '현재 {event} 전투 계획 배정이 없습니다.',
      battlePlanOne: '{event} 전투 계획 배정이 1건 있습니다.',
      battlePlanMany: '{event} 전투 계획 배정이 {count}건 있습니다.',
      transferNotInScope: '현재 권한 있는 이전 범위에서는 이전 준비 상태를 제공할 수 없습니다.',
      transferStatus:
        '이전 평가는 {outcome}입니다. 소유 기능이 계산한 요구 사항을 아래에 표시합니다.',
      territoryPlanNotFound: '{event}에 게시된 영토 계획 리비전이 연결되어 있지 않습니다.',
      territoryPlanAmbiguous:
        '{event}에 여러 게시 영토 계획 리비전이 연결되어 있어 임의로 선택하지 않습니다.',
      territoryPlanFound:
        '{event}에서는 {purpose} 용도로 {planName} 리비전 {revisionNumber}을 사용합니다.',
      rosterWriteHandoff:
        '여기서는 로스터를 변경할 수 없습니다. 일반 {event} 워크플로를 열어 변경하세요.',
    },
  },
  pl: {
    subtitle:
      'Pytaj o wydarzenia, własny roster i RSVP, własne przydziały, poradniki i obserwacje Sojuszu, opublikowane plany terytorium, dane gry ze źródłami oraz gotowość do transferu w dozwolonym zakresie.',
    authorizationHint: 'Odpowiedzi używają wyłącznie danych, które masz prawo zobaczyć.',
    possibleMatches: 'Możliwe dopasowania',
    detailsHeading: 'Szczegóły odpowiedzi',
    requirementsHeading: 'Wymagania transferu',
    datasetVersion: 'Wersja zbioru danych',
    evidenceStatus: 'Stan dowodów',
    checksum: 'Suma kontrolna',
    revisionLabel: 'Rewizja {revision}',
    participationSummary: 'RSVP: {response} · Rejestracja: {registration}',
    waitlistPosition: 'Pozycja na liście oczekujących: {position}',
    teamLabel: 'Zespół: {team}',
    sources: {
      participation: 'Udział',
      battle_plan_assignment: 'Przydział planu bitwy',
      transfer_assessment: 'Ocena transferu',
      territory_plan_revision: 'Opublikowany plan terytorium',
    },
    prompts: {
      heroFact: 'Z której generacji jest Amadeus?',
      rsvpWeek: 'Na co odpowiedziałem RSVP w tym tygodniu?',
      battleAssignment: 'Jaki jest mój przydział w Swordland?',
      transferStatus: 'Czego brakuje mi do transferu?',
      territoryPlan: 'Którego układu hive używamy na Bear Hunt?',
    },
    handoffs: { openRoster: 'Otwórz roster wydarzenia' },
    answers: {
      help: 'Mogę odpowiadać na podstawie autoryzowanych wydarzeń, własnego rosteru i RSVP, własnych przydziałów, poradników i obserwacji Sojuszu, opublikowanych planów terytorium, danych gry ze źródłami oraz statusu transferu w zakresie. Nie używam KingShot bez źródeł.',
      unsupported:
        'Mogę odpowiadać tylko z obsługiwanych autoryzowanych źródeł. Nie używam ogólnej wiedzy KingShot ani nie dokonuję zmian z Asystenta.',
      gameFactKnown:
        'Dane gry — {title}. Poniższe wartości ze źródłami pochodzą ze zbioru {datasetVersion}.',
      gameFactUnknown:
        'Dane gry — nie mam obsługiwanej znanej wartości dla {title}. Nie będę zgadywać.',
      gameFactConflicting:
        'Dane gry — utrzymywane dowody dla {title} są sprzeczne. Nie wybiorę wartości za Ciebie.',
      participationNone: 'Brak zapisanego RSVP lub rejestracji dla Ciebie na {event}.',
      participationFound: 'Twój udział w {event} pokazano poniżej.',
      participationWeekNone: 'Nie znaleziono Twojego RSVP ani rejestracji w tym tygodniu.',
      participationNotFound:
        'Nie znaleziono pasującego stanu RSVP, rejestracji ani listy oczekujących.',
      participationWeek: 'Znaleziono {count} rekord(y) udziału w tym tygodniu.',
      participationList: 'Znaleziono {count} pasujące rekord(y) udziału.',
      battlePlanNotFound:
        'Nie znaleziono nadchodzącego autoryzowanego wydarzenia z Twoim przydziałem.',
      battlePlanNone: 'Nie masz obecnie przydziału planu bitwy dla {event}.',
      battlePlanOne: 'Masz jeden przydział planu bitwy dla {event}.',
      battlePlanMany: 'Masz {count} przydziały planu bitwy dla {event}.',
      transferNotInScope: 'Nie mogę podać gotowości transferu z obecnego autoryzowanego zakresu.',
      transferStatus:
        'Twoja ocena transferu to {outcome}. Wymagania wyliczone przez właściciela są poniżej.',
      territoryPlanNotFound: 'Do {event} nie dołączono opublikowanej rewizji planu terytorium.',
      territoryPlanAmbiguous:
        'Do {event} dołączono kilka opublikowanych rewizji planu terytorium; nie wybiorę jednej po cichu.',
      territoryPlanFound: '{event} używa {planName}, rewizja {revisionNumber}, do {purpose}.',
      rosterWriteHandoff:
        'Nie mogę zmienić rosteru tutaj. Otwórz zwykły przepływ {event}, aby wprowadzić zmianę.',
    },
  },
  'pt-BR': {
    subtitle:
      'Pergunte sobre Eventos, seu roster e RSVPs, suas atribuições, guias e observações da Aliança, planos de território publicados, dados do jogo com fontes e sua prontidão para transferência quando estiver no escopo.',
    authorizationHint: 'As respostas usam apenas dados que você tem autorização para ver.',
    possibleMatches: 'Possíveis correspondências',
    detailsHeading: 'Detalhes da resposta',
    requirementsHeading: 'Requisitos de transferência',
    datasetVersion: 'Versão do conjunto de dados',
    evidenceStatus: 'Status da evidência',
    checksum: 'Checksum',
    revisionLabel: 'Revisão {revision}',
    participationSummary: 'RSVP: {response} · Inscrição: {registration}',
    waitlistPosition: 'Posição na lista de espera: {position}',
    teamLabel: 'Equipe: {team}',
    sources: {
      participation: 'Participação',
      battle_plan_assignment: 'Atribuição do plano de batalha',
      transfer_assessment: 'Avaliação de transferência',
      territory_plan_revision: 'Plano de território publicado',
    },
    prompts: {
      heroFact: 'De qual geração é Amadeus?',
      rsvpWeek: 'Para quais Eventos confirmei presença esta semana?',
      battleAssignment: 'Qual é minha atribuição em Swordland?',
      transferStatus: 'O que falta para minha transferência?',
      territoryPlan: 'Qual layout da colmeia estamos usando no Bear Hunt?',
    },
    handoffs: { openRoster: 'Abrir roster do Evento' },
    answers: {
      help: 'Posso responder a partir de Eventos autorizados, seu roster e RSVPs, suas atribuições, guias e observações da Aliança, planos de território publicados, dados do jogo com fontes e seu status de transferência no escopo. Não uso conhecimento de KingShot sem fonte.',
      unsupported:
        'Só posso responder a partir das fontes autorizadas compatíveis. Não uso conhecimento geral de KingShot nem faço alterações pelo Assistente.',
      gameFactKnown:
        'Dados do jogo — {title}. Os valores com fonte abaixo vêm do conjunto {datasetVersion}.',
      gameFactUnknown:
        'Dados do jogo — não tenho um valor conhecido e compatível para {title}. Não vou adivinhar.',
      gameFactConflicting:
        'Dados do jogo — as evidências mantidas para {title} estão em conflito. Não vou escolher um valor por você.',
      participationNone: 'Você não tem RSVP ou inscrição registrada para {event}.',
      participationFound: 'Sua participação em {event} está abaixo.',
      participationWeekNone: 'Não encontrei RSVP ou inscrição sua nesta semana.',
      participationNotFound:
        'Não encontrei um estado correspondente de RSVP, inscrição ou lista de espera para você.',
      participationWeek: 'Encontrei {count} registro(s) de participação seu(s) nesta semana.',
      participationList: 'Encontrei {count} registro(s) de participação correspondente(s).',
      battlePlanNotFound:
        'Não encontrei um próximo Evento autorizado com uma atribuição para você.',
      battlePlanNone: 'Você não tem uma atribuição de plano de batalha para {event} no momento.',
      battlePlanOne: 'Você tem uma atribuição de plano de batalha para {event}.',
      battlePlanMany: 'Você tem {count} atribuições de plano de batalha para {event}.',
      transferNotInScope:
        'Não posso fornecer prontidão de transferência a partir do seu escopo autorizado atual.',
      transferStatus:
        'Sua avaliação de transferência é {outcome}. Os requisitos calculados pelo recurso proprietário estão abaixo.',
      territoryPlanNotFound: 'Não há revisão publicada de plano de território anexada a {event}.',
      territoryPlanAmbiguous:
        'Há várias revisões publicadas de plano de território anexadas a {event}; não vou escolher uma silenciosamente.',
      territoryPlanFound:
        '{event} está usando {planName}, revisão {revisionNumber}, para {purpose}.',
      rosterWriteHandoff:
        'Não posso alterar o roster daqui. Abra o fluxo normal de {event} para fazer a alteração.',
    },
  },
  ru: {
    subtitle:
      'Спрашивайте о событиях, своём ростере и RSVP, своих назначениях, гайдах и наблюдениях Альянса, опубликованных планах территории, игровых данных с источниками и готовности к переносу в разрешённой области.',
    authorizationHint: 'Ответы используют только данные, которые вам разрешено видеть.',
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
    sources: {
      participation: 'Участие',
      battle_plan_assignment: 'Назначение боевого плана',
      transfer_assessment: 'Оценка переноса',
      territory_plan_revision: 'Опубликованный план территории',
    },
    prompts: {
      heroFact: 'К какому поколению относится Amadeus?',
      rsvpWeek: 'На какие события я ответил на этой неделе?',
      battleAssignment: 'Какое у меня назначение в Swordland?',
      transferStatus: 'Чего мне не хватает для переноса?',
      territoryPlan: 'Какую схему улья мы используем для Bear Hunt?',
    },
    handoffs: { openRoster: 'Открыть ростер события' },
    answers: {
      help: 'Я могу отвечать по разрешённым событиям, вашему ростеру и RSVP, вашим назначениям, гайдам и наблюдениям Альянса, опубликованным планам территории, игровым данным с источниками и статусу переноса в разрешённой области. Я не использую KingShot без источников.',
      unsupported:
        'Я могу отвечать только по поддерживаемым разрешённым источникам. Я не использую общие знания KingShot и не вношу изменения из Ассистента.',
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
    },
  },
  th: {
    subtitle:
      'ถามเกี่ยวกับอีเวนต์ โรสเตอร์และ RSVP ของคุณ งานที่ได้รับมอบหมาย คู่มือและข้อสังเกตของพันธมิตร แผนอาณาเขตที่เผยแพร่ ข้อมูลเกมที่มีแหล่งอ้างอิง และความพร้อมในการย้ายเมื่ออยู่ในขอบเขต',
    authorizationHint: 'คำตอบใช้เฉพาะข้อมูลที่คุณได้รับอนุญาตให้ดู',
    possibleMatches: 'รายการที่อาจตรงกัน',
    detailsHeading: 'รายละเอียดคำตอบ',
    requirementsHeading: 'ข้อกำหนดการย้าย',
    datasetVersion: 'เวอร์ชันชุดข้อมูล',
    evidenceStatus: 'สถานะหลักฐาน',
    checksum: 'เช็กซัม',
    revisionLabel: 'ฉบับแก้ไข {revision}',
    participationSummary: 'RSVP: {response} · ลงทะเบียน: {registration}',
    waitlistPosition: 'ลำดับคิว: {position}',
    teamLabel: 'ทีม: {team}',
    sources: {
      participation: 'การเข้าร่วม',
      battle_plan_assignment: 'งานแผนการรบ',
      transfer_assessment: 'การประเมินการย้าย',
      territory_plan_revision: 'แผนอาณาเขตที่เผยแพร่',
    },
    prompts: {
      heroFact: 'Amadeus เป็นรุ่นที่เท่าไร?',
      rsvpWeek: 'สัปดาห์นี้ฉัน RSVP อีเวนต์ใดบ้าง?',
      battleAssignment: 'งาน Swordland ของฉันคืออะไร?',
      transferStatus: 'ฉันยังขาดอะไรสำหรับการย้าย?',
      territoryPlan: 'เราใช้ผังไฮฟ์ใดสำหรับ Bear Hunt?',
    },
    handoffs: { openRoster: 'เปิดโรสเตอร์อีเวนต์' },
    answers: {
      help: 'ฉันตอบได้จากอีเวนต์ที่ได้รับอนุญาต โรสเตอร์และ RSVP ของคุณ งานของคุณ คู่มือและข้อสังเกตของพันธมิตร แผนอาณาเขตที่เผยแพร่ ข้อมูลเกมที่มีแหล่งอ้างอิง และสถานะการย้ายในขอบเขต โดยไม่ใช้ความรู้ KingShot ที่ไม่มีแหล่งอ้างอิง',
      unsupported:
        'ฉันตอบได้เฉพาะจากแหล่งข้อมูลที่รองรับและได้รับอนุญาต ไม่ใช้ความรู้ KingShot ทั่วไปและไม่เปลี่ยนแปลงข้อมูลจาก Assistant',
      gameFactKnown:
        'ข้อมูลเกม — {title} ค่าที่มีแหล่งอ้างอิงด้านล่างมาจากชุดข้อมูล {datasetVersion}',
      gameFactUnknown: 'ข้อมูลเกม — ไม่มีค่าที่ทราบและรองรับสำหรับ {title} ฉันจะไม่เดา',
      gameFactConflicting:
        'ข้อมูลเกม — หลักฐานที่ดูแลไว้สำหรับ {title} ขัดแย้งกัน ฉันจะไม่เลือกค่าแทนคุณ',
      participationNone: 'ไม่มี RSVP หรือการลงทะเบียนของคุณสำหรับ {event}',
      participationFound: 'การเข้าร่วม {event} ของคุณแสดงด้านล่าง',
      participationWeekNone: 'ไม่พบ RSVP หรือการลงทะเบียนของคุณในสัปดาห์นี้',
      participationNotFound: 'ไม่พบสถานะ RSVP การลงทะเบียน หรือคิวที่ตรงกับคุณ',
      participationWeek: 'พบระเบียนการเข้าร่วมของคุณ {count} รายการในสัปดาห์นี้',
      participationList: 'พบระเบียนการเข้าร่วมที่ตรงกัน {count} รายการ',
      battlePlanNotFound: 'ไม่พบอีเวนต์ที่ได้รับอนุญาตซึ่งมีงานสำหรับคุณ',
      battlePlanNone: 'ขณะนี้คุณไม่มีงานแผนการรบสำหรับ {event}',
      battlePlanOne: 'คุณมีงานแผนการรบหนึ่งรายการสำหรับ {event}',
      battlePlanMany: 'คุณมีงานแผนการรบ {count} รายการสำหรับ {event}',
      transferNotInScope: 'ฉันไม่สามารถให้ความพร้อมการย้ายจากขอบเขตที่ได้รับอนุญาตปัจจุบันของคุณ',
      transferStatus: 'ผลประเมินการย้ายของคุณคือ {outcome} ข้อกำหนดที่เจ้าของคำนวณแสดงด้านล่าง',
      territoryPlanNotFound: 'ไม่มีฉบับแผนอาณาเขตที่เผยแพร่แนบกับ {event}',
      territoryPlanAmbiguous:
        'มีฉบับแผนอาณาเขตที่เผยแพร่หลายฉบับแนบกับ {event}; ฉันจะไม่เลือกโดยเงียบ ๆ',
      territoryPlanFound: '{event} ใช้ {planName} ฉบับ {revisionNumber} สำหรับ {purpose}',
      rosterWriteHandoff:
        'ฉันเปลี่ยนโรสเตอร์จากที่นี่ไม่ได้ โปรดเปิดขั้นตอนปกติของ {event} เพื่อทำการเปลี่ยนแปลง',
    },
  },
  tr: {
    subtitle:
      'Etkinlikler, kendi roster ve RSVP durumun, görevlerin, İttifak rehberleri ve gözlemleri, yayımlanmış bölge planları, kaynaklı oyun verileri ve kapsam içindeki transfer hazırlığın hakkında sor.',
    authorizationHint: 'Yanıtlar yalnızca görmeye yetkili olduğun verileri kullanır.',
    possibleMatches: 'Olası eşleşmeler',
    detailsHeading: 'Yanıt ayrıntıları',
    requirementsHeading: 'Transfer gereksinimleri',
    datasetVersion: 'Veri kümesi sürümü',
    evidenceStatus: 'Kanıt durumu',
    checksum: 'Sağlama toplamı',
    revisionLabel: 'Revizyon {revision}',
    participationSummary: 'RSVP: {response} · Kayıt: {registration}',
    waitlistPosition: 'Bekleme listesi sırası: {position}',
    teamLabel: 'Takım: {team}',
    sources: {
      participation: 'Katılım',
      battle_plan_assignment: 'Savaş planı görevi',
      transfer_assessment: 'Transfer değerlendirmesi',
      territory_plan_revision: 'Yayımlanmış bölge planı',
    },
    prompts: {
      heroFact: 'Amadeus kaçıncı nesil?',
      rsvpWeek: 'Bu hafta hangi etkinliklere RSVP verdim?',
      battleAssignment: 'Swordland görevim nedir?',
      transferStatus: 'Transfer için neyim eksik?',
      territoryPlan: 'Bear Hunt için hangi hive düzenini kullanıyoruz?',
    },
    handoffs: { openRoster: 'Etkinlik rosterını aç' },
    answers: {
      help: 'Yetkili etkinliklerden, kendi roster ve RSVP durumundan, görevlerinden, İttifak rehberleri ve gözlemlerinden, yayımlanmış bölge planlarından, kaynaklı oyun verilerinden ve kapsam içindeki transfer durumundan yanıt verebilirim. Kaynaksız KingShot bilgisi kullanmam.',
      unsupported:
        'Yalnızca desteklenen yetkili kaynaklardan yanıt verebilirim. Genel KingShot bilgisi kullanmam ve Assistant üzerinden değişiklik yapmam.',
      gameFactKnown:
        'Oyun verisi — {title}. Aşağıdaki kaynaklı değerler {datasetVersion} veri kümesindendir.',
      gameFactUnknown:
        'Oyun verisi — {title} için desteklenen bilinen bir değer yok. Tahmin etmeyeceğim.',
      gameFactConflicting:
        'Oyun verisi — {title} için tutulan kanıtlar çelişiyor. Senin adına bir değer seçmeyeceğim.',
      participationNone: '{event} için kaydedilmiş RSVP veya kaydın yok.',
      participationFound: '{event} katılımın aşağıda gösteriliyor.',
      participationWeekNone: 'Bu hafta kaydedilmiş RSVP veya kaydın bulunamadı.',
      participationNotFound: 'Sana ait eşleşen RSVP, kayıt veya bekleme listesi durumu bulunamadı.',
      participationWeek: 'Bu hafta sana ait {count} katılım kaydı bulundu.',
      participationList: 'Sana ait {count} eşleşen katılım kaydı bulundu.',
      battlePlanNotFound: 'Senin için görev içeren yaklaşan yetkili bir etkinlik bulunamadı.',
      battlePlanNone: 'Şu anda {event} için savaş planı görevin yok.',
      battlePlanOne: '{event} için bir savaş planı görevin var.',
      battlePlanMany: '{event} için {count} savaş planı görevin var.',
      transferNotInScope:
        'Mevcut yetkili transfer kapsamından transfer hazırlığını sağlayamıyorum.',
      transferStatus:
        'Transfer değerlendirmen {outcome}. Sahip yeteneğin hesapladığı gereksinimler aşağıda.',
      territoryPlanNotFound: '{event} için yayımlanmış bir bölge planı revizyonu bağlı değil.',
      territoryPlanAmbiguous:
        '{event} için birden fazla yayımlanmış bölge planı revizyonu bağlı; sessizce birini seçmeyeceğim.',
      territoryPlanFound:
        '{event}, {purpose} için {planName} revizyon {revisionNumber} kullanıyor.',
      rosterWriteHandoff:
        'Rosterı buradan değiştiremem. Değişiklik yapmak için normal {event} akışını aç.',
    },
  },
  vi: {
    subtitle:
      'Hỏi về Sự kiện, roster và RSVP của bạn, nhiệm vụ của bạn, hướng dẫn và quan sát của Liên minh, kế hoạch lãnh thổ đã xuất bản, dữ liệu game có nguồn và mức sẵn sàng chuyển vương quốc khi bạn thuộc phạm vi.',
    authorizationHint: 'Câu trả lời chỉ dùng dữ liệu bạn được phép xem.',
    possibleMatches: 'Kết quả có thể khớp',
    detailsHeading: 'Chi tiết câu trả lời',
    requirementsHeading: 'Yêu cầu chuyển vương quốc',
    datasetVersion: 'Phiên bản bộ dữ liệu',
    evidenceStatus: 'Trạng thái bằng chứng',
    checksum: 'Checksum',
    revisionLabel: 'Bản sửa đổi {revision}',
    participationSummary: 'RSVP: {response} · Đăng ký: {registration}',
    waitlistPosition: 'Vị trí chờ: {position}',
    teamLabel: 'Đội: {team}',
    sources: {
      participation: 'Tham gia',
      battle_plan_assignment: 'Nhiệm vụ kế hoạch chiến đấu',
      transfer_assessment: 'Đánh giá chuyển vương quốc',
      territory_plan_revision: 'Kế hoạch lãnh thổ đã xuất bản',
    },
    prompts: {
      heroFact: 'Amadeus thuộc thế hệ nào?',
      rsvpWeek: 'Tuần này tôi đã RSVP sự kiện nào?',
      battleAssignment: 'Nhiệm vụ Swordland của tôi là gì?',
      transferStatus: 'Tôi còn thiếu gì để chuyển vương quốc?',
      territoryPlan: 'Chúng ta dùng bố cục hive nào cho Bear Hunt?',
    },
    handoffs: { openRoster: 'Mở roster Sự kiện' },
    answers: {
      help: 'Tôi có thể trả lời từ Sự kiện được phép, roster và RSVP của bạn, nhiệm vụ của bạn, hướng dẫn và quan sát Liên minh, kế hoạch lãnh thổ đã xuất bản, dữ liệu game có nguồn và trạng thái chuyển vương quốc trong phạm vi. Tôi không dùng kiến thức KingShot không có nguồn.',
      unsupported:
        'Tôi chỉ có thể trả lời từ các nguồn được phép và được hỗ trợ. Tôi không dùng kiến thức KingShot chung và không thay đổi dữ liệu từ Assistant.',
      gameFactKnown:
        'Dữ liệu game — {title}. Các giá trị có nguồn bên dưới đến từ bộ dữ liệu {datasetVersion}.',
      gameFactUnknown:
        'Dữ liệu game — tôi không có giá trị đã biết được hỗ trợ cho {title}. Tôi sẽ không đoán.',
      gameFactConflicting:
        'Dữ liệu game — bằng chứng được duy trì cho {title} đang mâu thuẫn. Tôi sẽ không chọn giá trị thay bạn.',
      participationNone: 'Không có RSVP hoặc đăng ký của bạn cho {event}.',
      participationFound: 'Thông tin tham gia {event} của bạn ở bên dưới.',
      participationWeekNone: 'Không tìm thấy RSVP hoặc đăng ký của bạn trong tuần này.',
      participationNotFound:
        'Không tìm thấy trạng thái RSVP, đăng ký hoặc danh sách chờ phù hợp cho bạn.',
      participationWeek: 'Tìm thấy {count} bản ghi tham gia của bạn trong tuần này.',
      participationList: 'Tìm thấy {count} bản ghi tham gia phù hợp cho bạn.',
      battlePlanNotFound: 'Không tìm thấy Sự kiện sắp tới được phép có nhiệm vụ cho bạn.',
      battlePlanNone: 'Hiện bạn không có nhiệm vụ kế hoạch chiến đấu cho {event}.',
      battlePlanOne: 'Bạn có một nhiệm vụ kế hoạch chiến đấu cho {event}.',
      battlePlanMany: 'Bạn có {count} nhiệm vụ kế hoạch chiến đấu cho {event}.',
      transferNotInScope:
        'Tôi không thể cung cấp mức sẵn sàng chuyển vương quốc từ phạm vi được phép hiện tại của bạn.',
      transferStatus:
        'Đánh giá chuyển vương quốc của bạn là {outcome}. Các yêu cầu do năng lực sở hữu tính toán được hiển thị bên dưới.',
      territoryPlanNotFound: 'Không có bản sửa đổi kế hoạch lãnh thổ đã xuất bản gắn với {event}.',
      territoryPlanAmbiguous:
        'Có nhiều bản sửa đổi kế hoạch lãnh thổ đã xuất bản gắn với {event}; tôi sẽ không tự chọn một bản.',
      territoryPlanFound:
        '{event} đang dùng {planName}, bản sửa đổi {revisionNumber}, cho {purpose}.',
      rosterWriteHandoff:
        'Tôi không thể thay đổi roster từ đây. Hãy mở luồng {event} bình thường để thực hiện thay đổi.',
    },
  },
  'zh-CN': {
    subtitle:
      '可询问活动、自己的名单和 RSVP、自己的任务、联盟指南与观察、已发布的领地方案、有来源的游戏数据，以及在授权范围内的转服准备情况。',
    authorizationHint: '回答只使用你有权查看的数据。',
    possibleMatches: '可能的匹配项',
    detailsHeading: '回答详情',
    requirementsHeading: '转服要求',
    datasetVersion: '数据集版本',
    evidenceStatus: '证据状态',
    checksum: '校验和',
    revisionLabel: '修订版 {revision}',
    participationSummary: 'RSVP：{response} · 报名：{registration}',
    waitlistPosition: '候补位置：{position}',
    teamLabel: '队伍：{team}',
    sources: {
      participation: '参与情况',
      battle_plan_assignment: '战斗计划任务',
      transfer_assessment: '转服评估',
      territory_plan_revision: '已发布领地方案',
    },
    prompts: {
      heroFact: 'Amadeus 是第几代？',
      rsvpWeek: '我这周 RSVP 了哪些活动？',
      battleAssignment: '我的 Swordland 任务是什么？',
      transferStatus: '我转服还缺什么？',
      territoryPlan: 'Bear Hunt 使用哪个蜂巢布局？',
    },
    handoffs: { openRoster: '打开活动名单' },
    answers: {
      help: '我可以根据授权活动、你的名单和 RSVP、你的任务、联盟指南与观察、已发布领地方案、有来源的游戏数据以及授权范围内的转服状态回答。不会使用无来源的 KingShot 知识。',
      unsupported:
        '我只能根据受支持且已授权的来源回答。不会使用一般 KingShot 知识，也不会从 Assistant 直接修改数据。',
      gameFactKnown: '游戏数据 — {title}。下方有来源的数值来自数据集 {datasetVersion}。',
      gameFactUnknown: '游戏数据 — {title} 没有受支持的已知值。我不会猜测。',
      gameFactConflicting: '游戏数据 — {title} 的维护证据存在冲突。我不会替你选择一个值。',
      participationNone: '{event} 没有你的 RSVP 或报名记录。',
      participationFound: '你在 {event} 的参与情况如下。',
      participationWeekNone: '本周没有找到你的 RSVP 或报名记录。',
      participationNotFound: '没有找到与你匹配的 RSVP、报名或候补状态。',
      participationWeek: '本周找到你的 {count} 条参与记录。',
      participationList: '找到你的 {count} 条匹配参与记录。',
      battlePlanNotFound: '没有找到包含你任务的、你有权查看的近期活动。',
      battlePlanNone: '你目前没有 {event} 的战斗计划任务。',
      battlePlanOne: '你有 1 个 {event} 战斗计划任务。',
      battlePlanMany: '你有 {count} 个 {event} 战斗计划任务。',
      transferNotInScope: '当前授权转服范围内无法提供你的转服准备情况。',
      transferStatus: '你的转服评估为 {outcome}。下方显示由所属能力计算的要求。',
      territoryPlanNotFound: '{event} 没有关联已发布的领地方案修订版。',
      territoryPlanAmbiguous: '{event} 关联了多个已发布领地方案修订版；我不会静默选择其中一个。',
      territoryPlanFound: '{event} 正在为 {purpose} 使用 {planName} 修订版 {revisionNumber}。',
      rosterWriteHandoff: '我无法从这里修改名单。请打开正常的 {event} 流程进行更改。',
    },
  },
  'zh-TW': {
    subtitle:
      '可詢問活動、自己的名單與 RSVP、自己的任務、聯盟指南與觀察、已發布的領地方案、有來源的遊戲資料，以及在授權範圍內的轉服準備狀況。',
    authorizationHint: '回答只使用你有權查看的資料。',
    possibleMatches: '可能的相符項目',
    detailsHeading: '回答詳情',
    requirementsHeading: '轉服要求',
    datasetVersion: '資料集版本',
    evidenceStatus: '證據狀態',
    checksum: '校驗和',
    revisionLabel: '修訂版 {revision}',
    participationSummary: 'RSVP：{response} · 報名：{registration}',
    waitlistPosition: '候補位置：{position}',
    teamLabel: '隊伍：{team}',
    sources: {
      participation: '參與狀況',
      battle_plan_assignment: '戰鬥計畫任務',
      transfer_assessment: '轉服評估',
      territory_plan_revision: '已發布領地方案',
    },
    prompts: {
      heroFact: 'Amadeus 是第幾代？',
      rsvpWeek: '我這週 RSVP 了哪些活動？',
      battleAssignment: '我的 Swordland 任務是什麼？',
      transferStatus: '我轉服還缺什麼？',
      territoryPlan: 'Bear Hunt 使用哪個蜂巢配置？',
    },
    handoffs: { openRoster: '開啟活動名單' },
    answers: {
      help: '我可以根據授權活動、你的名單與 RSVP、你的任務、聯盟指南與觀察、已發布領地方案、有來源的遊戲資料，以及授權範圍內的轉服狀態回答。不會使用無來源的 KingShot 知識。',
      unsupported:
        '我只能根據受支援且已授權的來源回答。不會使用一般 KingShot 知識，也不會從 Assistant 直接修改資料。',
      gameFactKnown: '遊戲資料 — {title}。下方有來源的數值來自資料集 {datasetVersion}。',
      gameFactUnknown: '遊戲資料 — {title} 沒有受支援的已知值。我不會猜測。',
      gameFactConflicting: '遊戲資料 — {title} 的維護證據存在衝突。我不會替你選擇一個值。',
      participationNone: '{event} 沒有你的 RSVP 或報名記錄。',
      participationFound: '你在 {event} 的參與狀況如下。',
      participationWeekNone: '本週沒有找到你的 RSVP 或報名記錄。',
      participationNotFound: '沒有找到與你相符的 RSVP、報名或候補狀態。',
      participationWeek: '本週找到你的 {count} 筆參與記錄。',
      participationList: '找到你的 {count} 筆相符參與記錄。',
      battlePlanNotFound: '沒有找到包含你任務、且你有權查看的近期活動。',
      battlePlanNone: '你目前沒有 {event} 的戰鬥計畫任務。',
      battlePlanOne: '你有 1 個 {event} 戰鬥計畫任務。',
      battlePlanMany: '你有 {count} 個 {event} 戰鬥計畫任務。',
      transferNotInScope: '目前授權轉服範圍內無法提供你的轉服準備狀況。',
      transferStatus: '你的轉服評估為 {outcome}。下方顯示由所屬能力計算的要求。',
      territoryPlanNotFound: '{event} 沒有關聯已發布的領地方案修訂版。',
      territoryPlanAmbiguous: '{event} 關聯了多個已發布領地方案修訂版；我不會靜默選擇其中一個。',
      territoryPlanFound: '{event} 正在為 {purpose} 使用 {planName} 修訂版 {revisionNumber}。',
      rosterWriteHandoff: '我無法從這裡修改名單。請開啟正常的 {event} 流程進行變更。',
    },
  },
} satisfies Record<NonEnglishLocale, AssistantExtensionStrings>;

export function assistantGameWorldExtension(locale: LocaleCode): MessageCatalogue {
  if (locale === 'en') return {};
  return { assistant: extensions[locale] };
}
