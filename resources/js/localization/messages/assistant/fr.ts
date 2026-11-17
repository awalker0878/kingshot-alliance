import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Assistant',
    title: 'Interroger votre Alliance',
    eyebrow: 'Assistant d’Alliance · Réponses autorisées',
    subtitle:
      'Demandez des informations sur les événements, votre roster et vos réponses, vos affectations, les guides et observations de l’Alliance, les plans de territoire publiés, les données de jeu sourcées et votre préparation au transfert lorsque vous êtes dans le périmètre.',
    authorizationHint:
      'Les réponses utilisent uniquement les données que vous êtes autorisé à consulter.',
    tryAsking: 'Essayez de demander',
    conversation: 'Conversation avec l’Assistant d’Alliance',
    youAsked: 'Vous avez demandé',
    possibleEvents: 'Événements possibles',
    openEvent: 'Ouvrir l’Événement',
    sourcesHeading: 'Sources utilisées',
    sourceTime: 'Date de la source : {time}',
    questionLabel: 'Interroger votre Alliance',
    questionPlaceholder: 'À quelle heure est Swordland et suis-je dans le roster ?',
    inputHint:
      '{count}/{max} caractères · Entrée pour demander · Maj+Entrée pour une nouvelle ligne',
    asking: 'Vérification des sources…',
    ask: 'Demander',
    notRecorded: 'Non renseigné',
    classifications: {
      operational_fact: 'Fait opérationnel',
      game_fact: 'Données du jeu',
      alliance_strategy: 'Stratégie de l’Alliance',
      observation: 'Observation',
      alliance_command: 'Aperçu du fonctionnaire',
      event_readiness: "Préparation à l ' événement",
      bear_hunt_run: "Bear Hunt en cours d'exécution",
      roster_freshness: 'Gouverneur observation fraîcheur',
      transfer_verification: 'Vérification des transferts',
      territory_comparison: 'Comparaison des territoires',
    },
    sources: {
      event: 'Événement',
      roster: 'Liste des membres',
      alliance_content: 'Guide de l’Alliance',
      observation: 'Observation',
      game_fact: 'Données du jeu',
      participation: 'Participation',
      battle_plan_assignment: 'Affectation du plan de bataille',
      transfer_assessment: 'Évaluation du transfert',
      territory_plan_revision: 'Plan de territoire publié',
      alliance_command: 'Aperçu du fonctionnaire',
      event_readiness: "Préparation à l ' événement",
      bear_hunt_run: "Bear Hunt en cours d'exécution",
      roster_freshness: 'Gouverneur observation fraîcheur',
      transfer_verification: 'Vérification des transferts',
      territory_comparison: 'Comparaison des territoires',
      alliance_settings: "Paramètres de l'alliance",
      alliance_governance_history: "Histoire de la gouvernance de l'alliance",
      alliance_roster_reconciliation: "Réconciliation des listes d ' alliance",
    },
    prompts: {
      swordland: 'À quelle heure est Swordland et suis-je dans le roster ?',
      nextEvent: 'Quel est mon prochain Événement ?',
      bearGuide: 'Que dit notre guide Bear Hunt ?',
      observation: 'Qu’avons-nous observé au sujet de notre adversaire ?',
      heroFact: 'De quelle génération est Amadeus ?',
      rsvpWeek: 'À quoi ai-je répondu cette semaine ?',
      battleAssignment: 'Quelle est mon affectation Swordland ?',
      transferStatus: 'Que me manque-t-il pour le transfert ?',
      territoryPlan: 'Quel plan de ruche utilisons-nous pour Bear Hunt ?',
      allianceCommand: "Qu'est-ce qui nécessite l'attention de l'agent?",
      eventReadiness: 'Notre prochain événement vérifié est-il prêt ?',
      rallyGaps: 'Quels écarts de Rallye restent pour le prochain événement ?',
      bearHuntHistory: 'Affichez notre récent historique de performances Bear Hunt.',
      progressionFreshness: 'Quelles observations du gouverneur sont obsolètes ou manquantes?',
      transferVerification: "Quels participants au transfert ont besoin d'une vérification ?",
      intelligenceChanges: 'Quels changements matériels l’intelligence a-t-elle connus récemment ?',
      territoryComparison: 'Comment le territoire observé se compare-t-il au plan publié?',
    },
    answers: {
      transferVerificationIncomplete:
        'Cet aperçu a évalué {assessed} participants au transfert sur {total}. Au moins {count} nécessitent une attention ; {unassessed} ne sont pas évalués ici. Ouvrez la préparation au transfert pour les vérifications actuelles.',
      help: 'Je peux répondre à partir des événements autorisés, de votre roster et de vos réponses, de vos affectations, des guides et observations de l’Alliance, des plans de territoire publiés, des données de jeu sourcées et de votre statut de transfert dans le périmètre. Je n’utilise pas de connaissances KingShot non sourcées.',
      unsupported:
        'Je peux répondre uniquement à partir des sources autorisées prises en charge. Je n’utilise pas de connaissances générales KingShot et je n’effectue aucune modification depuis l’Assistant.',
      unavailable:
        'L’Assistant d’Alliance ne peut pas vérifier ses sources pour le moment. Réessayez.',
      rateLimited: 'Vous posez des questions trop rapidement. Réessayez dans un instant.',
      validationError: 'Saisissez une question de 2 à {max} caractères.',
      noUpcomingEvent: 'Je n’ai trouvé aucun Événement à venir que vous êtes autorisé à consulter.',
      eventSubjectMissing: 'Indiquez l’Événement que vous voulez vérifier.',
      eventNotFound:
        'Je n’ai trouvé aucun Événement à venir autorisé correspondant à « {subject} ».',
      eventAmbiguous:
        'Plusieurs Événements correspondent à « {subject} ». Ouvrez ci-dessous celui que vous voulez.',
      eventTime: '{event} commence {startsAt}.',
      eventTimeNotRostered:
        '{event} commence {startsAt}. Vous n’êtes pas actuellement dans le roster.',
      notRostered: 'Vous n’êtes pas actuellement dans le roster de {event}.',
      eventTimeRostered:
        '{event} commence {startsAt}. Vous êtes dans {roster}. Rôle : {role} ; emplacement : {slot} ; statut : {status}.',
      rostered:
        'Vous êtes dans le roster de {event}, dans {roster}. Rôle : {role} ; emplacement : {slot} ; statut : {status}.',
      contentSubjectMissing: 'Indiquez l’Événement ou le sujet du guide de l’Alliance à vérifier.',
      contentNotFound:
        'Je n’ai trouvé aucun contenu d’Alliance publié correspondant à « {subject} ».',
      contentFound: 'Stratégie de l’Alliance — {title} : {excerpt}',
      observationSubjectMissing: 'Indiquez l’Alliance ou le sujet d’observation à vérifier.',
      observationNotFound:
        'Je n’ai trouvé aucune observation autorisée correspondant à « {subject} ».',
      observationFound: 'Observation — {title} : {observation}',
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
      ownerEventWriteHandoff:
        "Je ne peux pas faire ce changement à partir de l'Assistant. Ouvrez le workflow normal du propriétaire {event} pour continuer.",
      ownerWriteHandoff:
        "Je ne peux pas modifier l'état {owner} à partir de l'Assistant. Les faits autorisés actuels sont indiqués ci-dessous; ouvrez le workflow du propriétaire pour continuer.",
      allianceCommandAttention:
        "L'aperçu de l'agent contient {count} élément(s) factuel(s) nécessitant une attention particulière. Les sources autorisées sont indiquées ci-dessous.",
      allianceCommandAttentionNotAvailable:
        "Il n'y a pas de faits de vue d'ensemble autorisés pour cette alliance.",
      eventReadiness:
        'Le prochain Événement vérifié présente {count} point(s) bloquant sa préparation. La vue du responsable est affichée ci-dessous.',
      eventReadinessNotAvailable:
        "Je n'ai pas trouvé de préparation à l'événement vérifiée correspondante dans votre aperçu des agents autorisés.",
      rallyGaps:
        'Le prochain événement vérifié a {count} écart(s) de préparation au rallye. Les faits appartenant au Rallye sont présentés ci-dessous.',
      rallyGapsNotAvailable:
        "Je n'ai pas trouvé de données de préparation au rallye pour un événement vérifié correspondant.",
      bearHuntHistory:
        "J'ai trouvé {count} enregistrement(s) de chasse à l'ours autorisée récente. Les états enregistrés et acceptés de preuve restent explicites ci-dessous.",
      bearHuntHistoryNotAvailable:
        "Je n'ai pas pu trouver une chasse à l'ours autorisée avec un historique supporté.",
      progressionFreshness:
        '{count} Les observations du gouverneur sont obsolètes ou manquent dans la projection autorisée de la fraîcheur de la liste.',
      progressionFreshnessNotAvailable:
        "La fraîcheur des observations du gouverneur n'est pas disponible dans votre aperçu des agents autorisés.",
      transferVerification:
        "{count} Les participants au transfert doivent être vérifiés ou sont bloqués dans l'évaluation du propriétaire.",
      transferVerificationNotAvailable:
        "Il n'existe actuellement aucune projection de vérification de transfert autorisée.",
      territoryComparison:
        'La comparaison autorisée des territoires contient {count} différence(s) observée(s). Les observations manquantes et incompatibles restent explicites ci-dessous.',
      territoryComparisonNotAvailable:
        "Il n'existe pas de comparaison autorisée publiée entre les territoires.",
      allianceSettings:
        "Les paramètres actuels de l'alliance sont {name}, le nom de l'URL {slug}, la langue {language} et le fuseau horaire {timezone}.",
      allianceGovernanceHistory:
        "J'ai trouvé {count} événement(s) de gouvernance d'alliance autorisé(s) récent(s). Ce sont des faits d'audit du propriétaire en lecture seule.",
      allianceRosterReconciliation:
        "La dernière observation acceptée sur la liste contient {needsReview} élément(s) à examiner et {matched} correspondant à l'appartenance actuelle.",
      allianceRosterReconciliationNotAvailable:
        "Il n'y a pas encore d'observation de liste d'alliance acceptée disponible pour la réconciliation.",
    },
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
    handoffs: {
      openRoster: 'Ouvrir le roster de l’événement',
      openOwnerWorkflow: 'Ouvrir le workflow du propriétaire',
    },
    transferRequirements: {
      window_phase: 'Phase de la fenêtre de transfert',
      transfer_group: 'Groupe de transfert',
      power_cap: 'Plafond de puissance',
      invitation: 'Invitation',
      transfer_passes: 'Passes de transfert',
      in_game_rules: 'Règles en jeu',
    },
    transferStates: {
      met: 'Satisfait',
      unmet: 'Non satisfait',
      unknown: 'Inconnu',
      stale: 'Périmé',
      conflicting: 'Contradictoire',
      not_applicable: 'Non applicable',
    },
    requirementValues: 'Actuel : {actual} · Requis : {required}',
  },
  intelligenceChange: {
    title: 'Changements récents du renseignement',
    subtitle:
      'Changements sourcés dérivés de l’historique autorisé. Ils décrivent des observations, pas une intention stratégique.',
    empty: 'Aucun changement matériel de renseignement n’est disponible pour cette portée.',
    source: 'Source',
    observed: 'Observé',
    baseline: 'Référence',
    current: 'Actuel',
    previous: 'Précédent',
    viewSource: 'Voir la source',
    material: 'Changement matériel',
    attention: 'À vérifier',
  },
} satisfies MessageCatalogue;

export default messages;
