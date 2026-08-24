import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Assistant',
    title: 'Ask your Alliance',
    eyebrow: 'Alliance Assistant · Authorized answers',
    subtitle:
      'Ask about Events, your roster, Alliance guides, and observations. Answers are grounded in sources you can already view.',
    authorizationHint: 'Answers use only Alliance data you are authorized to see.',
    tryAsking: 'Try asking',
    conversation: 'Alliance Assistant conversation',
    youAsked: 'You asked',
    possibleEvents: 'Possible Events',
    openEvent: 'Open Event',
    sourcesHeading: 'Sources used',
    sourceTime: 'Source time: {time}',
    questionLabel: 'Ask your Alliance',
    questionPlaceholder: 'What time is Swordland and am I rostered?',
    inputHint: '{count}/{max} characters · Enter to ask · Shift+Enter for a new line',
    asking: 'Checking sources…',
    ask: 'Ask',
    notRecorded: 'Not recorded',
    classifications: {
      operational_fact: 'Operational fact',
      game_fact: 'Game data',
      alliance_strategy: 'Alliance strategy',
      observation: 'Observation',
    },
    sources: {
      event: 'Event',
      roster: 'Roster',
      alliance_content: 'Alliance guide',
      observation: 'Observation',
      game_fact: 'Game data',
    },
    prompts: {
      swordland: 'What time is Swordland and am I rostered?',
      nextEvent: 'What is my next Event?',
      bearGuide: 'What does our Bear Hunt guide say?',
      observation: 'What have we observed about our opponent?',
    },
    answers: {
      help: 'I can answer from Events, your roster, Alliance guides, and authorized observations. I do not use unsourced KingShot knowledge.',
      unsupported:
        'I can only answer from authorized Events, your roster, Alliance guides, and observations. I cannot make changes from here.',
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
      contentSubjectMissing: 'Name the Event or topic whose Alliance guide you want me to check.',
      contentNotFound: 'I could not find published Alliance content matching “{subject}”.',
      contentFound: 'Alliance strategy — {title}: {excerpt}',
      observationSubjectMissing: 'Name the Alliance or observation subject you want me to check.',
      observationNotFound: 'I could not find an authorized observation matching “{subject}”.',
      observationFound: 'Observation — {title}: {observation}',
    },
  },
} satisfies MessageCatalogue;

export default messages;
