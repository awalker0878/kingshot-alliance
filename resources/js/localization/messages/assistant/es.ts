import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Asistente',
    title: 'Pregunta a tu Alianza',
    eyebrow: 'Asistente de Alianza · Respuestas autorizadas',
    subtitle:
      'Pregunta por eventos, tu roster y confirmaciones, tus asignaciones, guías y observaciones de la Alianza, planes de territorio publicados, datos del juego con fuentes y tu preparación para transferirte cuando estés dentro del ámbito.',
    authorizationHint: 'Las respuestas usan solo datos que tienes autorización para ver.',
    tryAsking: 'Prueba a preguntar',
    conversation: 'Conversación con el Asistente de Alianza',
    youAsked: 'Preguntaste',
    possibleEvents: 'Eventos posibles',
    openEvent: 'Abrir Evento',
    sourcesHeading: 'Fuentes utilizadas',
    sourceTime: 'Fecha de la fuente: {time}',
    questionLabel: 'Pregunta a tu Alianza',
    questionPlaceholder: '¿A qué hora es Swordland y estoy en el roster?',
    inputHint: '{count}/{max} caracteres · Enter para preguntar · Mayús+Enter para nueva línea',
    asking: 'Consultando fuentes…',
    ask: 'Preguntar',
    notRecorded: 'No registrado',
    classifications: {
      operational_fact: 'Dato operativo',
      game_fact: 'Datos del juego',
      alliance_strategy: 'Estrategia de la Alianza',
      observation: 'Observación',
      alliance_command: 'Descripción general del funcionario',
      event_readiness: 'Preparación para eventos',
      bear_hunt_run: 'Caza del oso',
      roster_freshness: 'Gobernador de observación de frescura',
      transfer_verification: 'Verificación de transferencias',
      territory_comparison: 'Comparación de territorios',
    },
    sources: {
      event: 'Evento',
      roster: 'Lista de miembros',
      alliance_content: 'Guía de la Alianza',
      observation: 'Observación',
      game_fact: 'Datos del juego',
      participation: 'Participación',
      battle_plan_assignment: 'Asignación del plan de batalla',
      transfer_assessment: 'Evaluación de transferencia',
      territory_plan_revision: 'Plan de territorio publicado',
      alliance_command: 'Descripción general del funcionario',
      event_readiness: 'Preparación para eventos',
      bear_hunt_run: 'Caza del oso',
      roster_freshness: 'Gobernador de observación de frescura',
      transfer_verification: 'Verificación de transferencias',
      territory_comparison: 'Comparación de territorios',
      alliance_settings: 'Configuración de alianza',
      alliance_governance_history: 'Historia de la gobernanza de la alianza',
      alliance_roster_reconciliation: 'Reconciliación de la lista de alianzas',
    },
    prompts: {
      swordland: '¿A qué hora es Swordland y estoy en el roster?',
      nextEvent: '¿Cuál es mi próximo Evento?',
      bearGuide: '¿Qué dice nuestra guía de Bear Hunt?',
      observation: '¿Qué hemos observado sobre nuestro oponente?',
      heroFact: '¿De qué generación es Amadeus?',
      rsvpWeek: '¿A qué confirmé asistencia esta semana?',
      battleAssignment: '¿Cuál es mi asignación de Swordland?',
      transferStatus: '¿Qué me falta para transferirme?',
      territoryPlan: '¿Qué diseño de colmena usamos para Bear Hunt?',
      allianceCommand: '¿Qué necesita la atención del oficial?',
      eventReadiness: '¿Está listo nuestro próximo evento verificado?',
      rallyGaps: '¿Qué puestos de Rally quedan para el próximo evento?',
      bearHuntHistory: 'Muestra nuestro historial de rendimiento reciente de Bear Hunt.',
      progressionFreshness: '¿Qué observaciones del gobernador están obsoletas o faltan?',
      transferVerification: '¿Qué participantes de Transfer necesitan verificación?',
      intelligenceChanges: '¿Qué Inteligencia material ha cambiado recientemente?',
      territoryComparison: '¿Cómo se compara el Territorio observado con el plan publicado?',
    },
    answers: {
      transferVerificationIncomplete:
        'Este resumen evaluó a {assessed} de {total} participantes del traslado. Al menos {count} necesitan atención; {unassessed} no se han evaluado aquí. Abre la preparación del traslado para las comprobaciones actuales.',
      help: 'Puedo responder desde eventos autorizados, tu roster y confirmaciones, tus asignaciones, guías y observaciones de la Alianza, planes de territorio publicados, datos del juego con fuentes y tu estado de transferencia dentro del ámbito. No uso conocimiento de KingShot sin fuentes.',
      unsupported:
        'Solo puedo responder desde fuentes autorizadas compatibles. No uso conocimiento general de KingShot ni hago cambios desde el Asistente.',
      unavailable:
        'El Asistente de Alianza no puede consultar sus fuentes ahora. Inténtalo de nuevo.',
      rateLimited: 'Estás preguntando demasiado rápido. Inténtalo de nuevo en breve.',
      validationError: 'Escribe una pregunta de entre 2 y {max} caracteres.',
      noUpcomingEvent: 'No encontré ningún Evento próximo que estés autorizado a ver.',
      eventSubjectMissing: 'Indica el Evento que quieres consultar.',
      eventNotFound: 'No encontré ningún Evento próximo autorizado que coincida con «{subject}».',
      eventAmbiguous:
        'Encontré más de un Evento que coincide con «{subject}». Abre abajo el que quieras.',
      eventTime: '{event} comienza {startsAt}.',
      eventTimeNotRostered: '{event} comienza {startsAt}. Actualmente no estás en el roster.',
      notRostered: 'Actualmente no estás en el roster de {event}.',
      eventTimeRostered:
        '{event} comienza {startsAt}. Estás en {roster}. Rol: {role}; puesto: {slot}; estado: {status}.',
      rostered:
        'Estás en el roster de {event}, en {roster}. Rol: {role}; puesto: {slot}; estado: {status}.',
      contentSubjectMissing: 'Indica el Evento o tema cuya guía de la Alianza quieres consultar.',
      contentNotFound:
        'No encontré contenido publicado de la Alianza que coincida con «{subject}».',
      contentFound: 'Estrategia de la Alianza — {title}: {excerpt}',
      observationSubjectMissing:
        'Indica la Alianza o el tema de observación que quieres consultar.',
      observationNotFound: 'No encontré una observación autorizada que coincida con «{subject}».',
      observationFound: 'Observación — {title}: {observation}',
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
      ownerEventWriteHandoff:
        'No puedo hacer este cambio desde el Asistente. Abra el flujo de trabajo normal del propietario {event} para continuar.',
      ownerWriteHandoff:
        'No puedo cambiar el estado {owner} desde el Asistente. Los hechos autorizados actuales se muestran a continuación; abra el flujo de trabajo del propietario para continuar.',
      allianceCommandAttention:
        'La descripción general del oficial tiene {count} elemento(s) factuales que necesitan atención. Las fuentes autorizadas se muestran a continuación.',
      allianceCommandAttentionNotAvailable:
        'No hay datos de resumen de oficial autorizado disponibles para esta alianza.',
      eventReadiness:
        'El siguiente evento verificado tiene {count} elementos de preparación de bloqueo. Su proyección propietaria se muestra a continuación.',
      eventReadinessNotAvailable:
        'No pude encontrar la preparación verificada para eventos que coincida en su vista general de oficiales autorizados.',
      rallyGaps:
        'El próximo evento verificado tiene {count} brecha(s) de preparación para el Rally. Los hechos de propiedad de Rally se muestran a continuación.',
      rallyGapsNotAvailable:
        'No pude encontrar datos de preparación de Rally para un evento verificado coincidente.',
      bearHuntHistory:
        'He encontrado {count} registro(s) de caza de osos autorizada reciente. Los estados de evidencia registrados y aceptados permanecen explícitos a continuación.',
      bearHuntHistoryNotAvailable:
        'No pude encontrar una carrera de Bear Hunt completa autorizada con historial respaldado.',
      progressionFreshness:
        '{count} observación(es) del gobernador están obsoletas o faltan en la proyección autorizada de la frescura de la lista.',
      progressionFreshnessNotAvailable:
        'La frecuencia de observación del gobernador no está disponible en su vista general del oficial autorizado.',
      transferVerification:
        '{count} Los participantes de la transferencia necesitan verificación o están bloqueados en la evaluación del propietario.',
      transferVerificationNotAvailable:
        'No existe actualmente una proyección de verificación de transferencia autorizada.',
      territoryComparison:
        'La comparación autorizada de Territorios contiene {count} diferencia(s) observada(s). Las observaciones faltantes e incompatibles se exponen explícitamente a continuación.',
      territoryComparisonNotAvailable:
        'No hay una comparación de territorios publicada autorizada disponible.',
      allianceSettings:
        'La configuración actual de la alianza es {name}, nombre de URL {slug}, idioma {language} y zona horaria {timezone}.',
      allianceGovernanceHistory:
        'He encontrado {count} eventos de gobernanza de alianzas autorizados recientes. Estos son hechos de auditoría de propietario de solo lectura.',
      allianceRosterReconciliation:
        'La última observación aceptada de la lista tiene {needsReview} artículo(s) que necesitan revisión y {matched} que coinciden con la membresía actual.',
      allianceRosterReconciliationNotAvailable:
        'Todavía no hay observaciones de la lista de alianzas aceptadas disponibles para conciliar.',
    },
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
    handoffs: {
      openRoster: 'Abrir roster del evento',
      openOwnerWorkflow: 'Flujo de trabajo de propietario abierto',
    },
    transferRequirements: {
      window_phase: 'Fase de la ventana de transferencia',
      transfer_group: 'Grupo de transferencia',
      power_cap: 'Límite de poder',
      invitation: 'Invitación',
      transfer_passes: 'Pases de transferencia',
      in_game_rules: 'Reglas del juego',
    },
    transferStates: {
      met: 'Cumplido',
      unmet: 'No cumplido',
      unknown: 'Desconocido',
      stale: 'Desactualizado',
      conflicting: 'En conflicto',
      not_applicable: 'No aplicable',
    },
    requirementValues: 'Actual: {actual} · Requerido: {required}',
  },
  intelligenceChange: {
    title: 'Cambios recientes de inteligencia',
    subtitle:
      'Cambios citados con fuente derivados del historial autorizado. Describen observaciones, no intención estratégica.',
    empty: 'No hay cambios materiales de inteligencia disponibles para este ámbito.',
    source: 'Fuente',
    observed: 'Observado',
    baseline: 'Referencia',
    current: 'Actual',
    previous: 'Anterior',
    viewSource: 'Ver fuente',
    material: 'Cambio material',
    attention: 'Requiere verificación',
  },
} satisfies MessageCatalogue;

export default messages;
