import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Assistant',
    title: 'Ask your Alliance',
    eyebrow: 'Alliance Assistant · Authorized answers',
    subtitle:
      'Ask about Events, your roster and RSVPs, your assignments, Alliance guides and observations, published territory plans, source-backed Game data, and your transfer readiness when you are in scope.',
    authorizationHint: 'Answers use only data you are authorized to see.',
    tryAsking: 'Try asking',
    conversation: 'Alliance Assistant conversation',
    youAsked: 'You asked',
    possibleEvents: 'Possible Events',
    possibleMatches: 'Possible matches',
    openEvent: 'Open Event',
    sourcesHeading: 'Sources used',
    detailsHeading: 'Answer details',
    requirementsHeading: 'Transfer requirements',
    sourceTime: 'Source time: {time}',
    questionLabel: 'Ask your Alliance',
    questionPlaceholder: 'What generation is Amadeus?',
    inputHint: '{count}/{max} characters · Enter to ask · Shift+Enter for a new line',
    asking: 'Checking sources…',
    ask: 'Ask',
    notRecorded: 'Not recorded',
    datasetVersion: 'Dataset version',
    evidenceStatus: 'Evidence status',
    checksum: 'Checksum',
    revisionLabel: 'Revision {revision}',
    participationSummary: 'RSVP: {response} · Registration: {registration}',
    waitlistPosition: 'Waitlist position: {position}',
    teamLabel: 'Team: {team}',
    classifications: {
      operational_fact: 'Operational fact',
      game_fact: 'Game data',
      alliance_command: 'Officer overview',
      event_readiness: 'Event readiness',
      bear_hunt_run: 'Bear Hunt run',
      roster_freshness: 'Governor observation freshness',
      transfer_verification: 'Transfer verification',
      territory_comparison: 'Territory comparison',
      alliance_strategy: 'Alliance strategy',
      observation: 'Observation',
    },
    sources: {
      event: 'Event',
      roster: 'Roster',
      participation: 'Participation',
      battle_plan_assignment: 'Battle plan assignment',
      transfer_assessment: 'Transfer assessment',
      territory_plan_revision: 'Published territory plan',
      alliance_content: 'Alliance guide',
      observation: 'Observation',
      game_fact: 'Game data',
      alliance_command: 'Officer overview',
      event_readiness: 'Event readiness',
      bear_hunt_run: 'Bear Hunt run',
      roster_freshness: 'Governor observation freshness',
      transfer_verification: 'Transfer verification',
      territory_comparison: 'Territory comparison',
    },
    prompts: {
      swordland: 'What time is Swordland and am I rostered?',
      nextEvent: 'What is my next Event?',
      bearGuide: 'What does our Bear Hunt guide say?',
      observation: 'What have we observed about our opponent?',
      heroFact: 'What generation is Amadeus?',
      rsvpWeek: 'What did I RSVP for this week?',
      battleAssignment: 'What is my Swordland assignment?',
      transferStatus: 'What am I missing for transfer?',
      territoryPlan: 'Which hive layout are we using for Bear Hunt?',
      allianceCommand: 'What needs officer attention?',
      eventReadiness: 'Is our next verified Event ready?',
      rallyGaps: 'What Rally gaps remain for the next Event?',
      bearHuntHistory: 'Show our recent Bear Hunt performance history.',
      progressionFreshness: 'Which Governor observations are stale or missing?',
      transferVerification: 'Which Transfer participants need verification?',
      intelligenceChanges: 'What material Intelligence changed recently?',
      territoryComparison: 'How does observed Territory compare with the published plan?',
    },
    handoffs: {
      openRoster: 'Open Event roster',
      openOwnerWorkflow: 'Open owner workflow',
    },
    answers: {
      help: 'I can answer from authorized Events, your roster and RSVPs, your assignments, Alliance guides and observations, published territory plans, source-backed Game data, and your transfer readiness when you are in scope. I do not use unsourced KingShot knowledge.',
      unsupported:
        'I can only answer from the supported authorized sources. I do not use general KingShot knowledge or make changes from the Assistant.',
      unavailable: 'Alliance Assistant cannot check its sources right now. Try again.',
      rateLimited: 'You are asking too quickly. Try again shortly.',
      validationError: 'Enter a question between 2 and {max} characters.',
      noUpcomingEvent: 'I could not find an upcoming Event you are authorized to view.',
      eventSubjectMissing: 'Name the Event you want me to check.',
      eventNotFound: 'I could not find an authorized upcoming Event matching “{subject}”.',
      eventAmbiguous:
        'I found more than one Event matching “{subject}”. Open the Event you mean below.',
      eventTime: '{event} starts {startsAt}.',
      eventTimeNotRostered: '{event} starts {startsAt}. You are not currently rostered.',
      notRostered: 'You are not currently rostered for {event}.',
      eventTimeRostered:
        '{event} starts {startsAt}. You are rostered in {roster}. Role: {role}; slot: {slot}; status: {status}.',
      rostered:
        'You are rostered for {event} in {roster}. Role: {role}; slot: {slot}; status: {status}.',
      gameFactKnown:
        'Game data — {title}. The source-backed values are shown below from dataset {datasetVersion}.',
      gameFactUnknown:
        'Game data — I do not have a supported known value for {title}. The source/release status is shown below; I will not guess.',
      gameFactConflicting:
        'Game data — the maintained evidence for {title} is conflicting. I will not choose a value on your behalf.',
      participationNone: 'You have no recorded RSVP or registration for {event}.',
      participationFound: 'Your participation for {event} is shown below.',
      participationWeekNone: 'I found no recorded RSVP or registration for you this week.',
      participationNotFound:
        'I found no matching recorded RSVP, registration, or waitlist state for you.',
      participationWeek: 'I found {count} Event participation record(s) for you this week.',
      participationList: 'I found {count} matching Event participation record(s) for you.',
      battlePlanNotFound:
        'I could not find an upcoming authorized Event with an assignment for you.',
      battlePlanNone: 'You do not currently have a battle-plan assignment for {event}.',
      battlePlanOne: 'You have one battle-plan assignment for {event}.',
      battlePlanMany: 'You have {count} battle-plan assignments for {event}.',
      transferNotInScope:
        'I cannot provide transfer readiness from your current authorized transfer scope.',
      transferStatus:
        'Your transfer assessment is {outcome}. The owner-calculated requirements are shown below.',
      territoryPlanNotFound: 'There is no published territory-plan revision attached to {event}.',
      territoryPlanAmbiguous:
        'More than one published territory-plan revision is attached to {event}; I will not choose one silently.',
      territoryPlanFound: '{event} is using {planName}, revision {revisionNumber}, for {purpose}.',
      rosterWriteHandoff:
        'I cannot change the roster from here. Open the normal {event} workflow to make the change.',
      ownerEventWriteHandoff:
        'I cannot make this change from the Assistant. Open the normal {event} owner workflow to continue.',
      ownerWriteHandoff:
        'I cannot change {owner} state from the Assistant. The current authorized facts are shown below; open the owner workflow to continue.',
      contentSubjectMissing: 'Name the Event or topic whose Alliance guide you want me to check.',
      contentNotFound: 'I could not find published Alliance content matching “{subject}”.',
      contentFound: 'Alliance strategy — {title}: {excerpt}',
      observationSubjectMissing: 'Name the Alliance or observation subject you want me to check.',
      observationNotFound: 'I could not find an authorized observation matching “{subject}”.',
      observationFound: 'Observation — {title}: {observation}',
      allianceCommandAttention:
        'The officer overview has {count} factual item(s) needing attention. The authorized sources are shown below.',
      allianceCommandAttentionNotAvailable:
        'There are no authorized officer-overview facts available for this Alliance.',
      eventReadiness:
        'The next verified Event has {count} blocking readiness item(s). Its owner projection is shown below.',
      eventReadinessNotAvailable:
        'I could not find matching verified Event readiness in your authorized officer overview.',
      rallyGaps:
        'The next verified Event has {count} Rally readiness gap(s). The Rally-owned facts are shown below.',
      rallyGapsNotAvailable:
        'I could not find Rally readiness facts for a matching verified Event.',
      bearHuntHistory:
        'I found {count} recent authorized Bear Hunt run record(s). Recorded and accepted-evidence states remain explicit below.',
      bearHuntHistoryNotAvailable:
        'I could not find an authorized completed Bear Hunt run with supported history.',
      progressionFreshness:
        '{count} Governor observation(s) are stale or missing in the authorized roster-freshness projection.',
      progressionFreshnessNotAvailable:
        'Governor observation freshness is not available in your authorized officer overview.',
      transferVerification:
        '{count} Transfer participant(s) need verification or are blocked in the owner assessment.',
      transferVerificationNotAvailable:
        'There is no current authorized Transfer verification projection.',
      territoryComparison:
        'The authorized Territory comparison contains {count} observed difference(s). Missing and incompatible observations remain explicit below.',
      territoryComparisonNotAvailable:
        'There is no authorized published Territory comparison available.',
    },
  },
} satisfies MessageCatalogue;

export default messages;
