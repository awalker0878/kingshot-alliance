import type { MessageCatalogue } from '../../types';
const messages = {
  assistant: {
    navigation: 'Asisten',
    title: 'Tanya Aliansimu',
    eyebrow: 'Asisten Aliansi · Jawaban berizin',
    subtitle:
      'Tanyakan tentang Event, roster-mu, panduan Aliansi, dan observasi. Jawaban didasarkan pada sumber yang memang sudah boleh kamu lihat.',
    authorizationHint: 'Jawaban hanya menggunakan data Aliansi yang berhak kamu lihat.',
    tryAsking: 'Coba tanyakan',
    conversation: 'Percakapan Asisten Aliansi',
    youAsked: 'Kamu bertanya',
    possibleEvents: 'Event yang mungkin',
    openEvent: 'Buka Event',
    sourcesHeading: 'Sumber yang digunakan',
    sourceTime: 'Waktu sumber: {time}',
    questionLabel: 'Tanya Aliansimu',
    questionPlaceholder: 'Swordland jam berapa dan apakah saya masuk roster?',
    inputHint: '{count}/{max} karakter · Enter untuk bertanya · Shift+Enter untuk baris baru',
    asking: 'Memeriksa sumber…',
    ask: 'Tanya',
    notRecorded: 'Belum tercatat',
    classifications: {
      operational_fact: 'Fakta operasional',
      game_fact: 'Data game',
      alliance_strategy: 'Strategi Aliansi',
      observation: 'Observasi',
    },
    sources: {
      event: 'Event',
      roster: 'Roster',
      alliance_content: 'Panduan Aliansi',
      observation: 'Observasi',
      game_fact: 'Data game',
    },
    prompts: {
      swordland: 'Swordland jam berapa dan apakah saya masuk roster?',
      nextEvent: 'Apa Event saya berikutnya?',
      bearGuide: 'Apa isi panduan Bear Hunt kita?',
      observation: 'Apa yang sudah kita amati tentang lawan?',
    },
    answers: {
      help: 'Saya dapat menjawab dari Event, roster-mu, panduan Aliansi, dan observasi yang berizin. Saya tidak memakai pengetahuan KingShot tanpa sumber.',
      unsupported:
        'Saya hanya dapat menjawab dari Event yang berizin, roster-mu, panduan Aliansi, dan observasi. Saya tidak dapat membuat perubahan dari sini.',
      unavailable: 'Asisten Aliansi tidak dapat memeriksa sumber saat ini. Coba lagi.',
      rateLimited: 'Kamu bertanya terlalu cepat. Coba lagi sebentar lagi.',
      validationError: 'Masukkan pertanyaan antara 2 dan {max} karakter.',
      noUpcomingEvent: 'Saya tidak menemukan Event mendatang yang boleh kamu lihat.',
      eventSubjectMissing: 'Sebutkan Event yang ingin diperiksa.',
      eventNotFound: 'Saya tidak menemukan Event mendatang berizin yang cocok dengan “{subject}”.',
      eventAmbiguous:
        'Saya menemukan lebih dari satu Event yang cocok dengan “{subject}”. Buka Event yang kamu maksud di bawah.',
      eventTime: '{event} dimulai {startsAt}.',
      eventTimeNotRostered: '{event} dimulai {startsAt}. Saat ini kamu belum masuk roster.',
      notRostered: 'Saat ini kamu belum masuk roster untuk {event}.',
      eventTimeRostered:
        '{event} dimulai {startsAt}. Kamu berada di {roster}. Peran: {role}; slot: {slot}; status: {status}.',
      rostered:
        'Kamu masuk roster {event} di {roster}. Peran: {role}; slot: {slot}; status: {status}.',
      contentSubjectMissing: 'Sebutkan Event atau topik panduan Aliansi yang ingin diperiksa.',
      contentNotFound: 'Saya tidak menemukan konten Aliansi terbit yang cocok dengan “{subject}”.',
      contentFound: 'Strategi Aliansi — {title}: {excerpt}',
      observationSubjectMissing: 'Sebutkan Aliansi atau topik observasi yang ingin diperiksa.',
      observationNotFound: 'Saya tidak menemukan observasi berizin yang cocok dengan “{subject}”.',
      observationFound: 'Observasi — {title}: {observation}',
    },
  },
} satisfies MessageCatalogue;
export default messages;
