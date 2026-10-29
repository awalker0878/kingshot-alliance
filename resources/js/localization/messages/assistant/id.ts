import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Asisten',
    title: 'Tanya Aliansimu',
    eyebrow: 'Asisten Aliansi · Jawaban berizin',
    subtitle:
      'Tanyakan Event, roster dan RSVP Anda, tugas Anda, panduan dan observasi Alliance, rencana wilayah yang diterbitkan, data game bersumber, serta kesiapan transfer Anda saat berada dalam cakupan.',
    authorizationHint: 'Jawaban hanya menggunakan data yang boleh Anda lihat.',
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
      alliance_command: 'Petugas',
      event_readiness: 'Kesiapan Acara',
      bear_hunt_run: 'Bear Hunt berjalan',
      roster_freshness: 'Kesegaran pengamatan gubernur',
      transfer_verification: 'Verifikasi transfer',
      territory_comparison: 'Perbandingan wilayah',
    },
    sources: {
      event: 'Peristiwa',
      roster: 'Daftar anggota',
      alliance_content: 'Panduan Aliansi',
      observation: 'Observasi',
      game_fact: 'Data game',
      participation: 'Partisipasi',
      battle_plan_assignment: 'Tugas rencana pertempuran',
      transfer_assessment: 'Penilaian transfer',
      territory_plan_revision: 'Rencana wilayah terbit',
      alliance_command: 'Petugas',
      event_readiness: 'Kesiapan Acara',
      bear_hunt_run: 'Bear Hunt berjalan',
      roster_freshness: 'Kesegaran pengamatan gubernur',
      transfer_verification: 'Verifikasi transfer',
      territory_comparison: 'Perbandingan wilayah',
      alliance_settings: 'pengaturan aliansi',
      alliance_governance_history: 'Riwayat tata kelola Aliansi',
      alliance_roster_reconciliation: 'rekonsiliasi daftar aliansi',
    },
    prompts: {
      swordland: 'Swordland jam berapa dan apakah saya masuk roster?',
      nextEvent: 'Apa Event saya berikutnya?',
      bearGuide: 'Apa isi panduan Bear Hunt kita?',
      observation: 'Apa yang sudah kita amati tentang lawan?',
      heroFact: 'Amadeus generasi berapa?',
      rsvpWeek: 'Event apa yang saya RSVP minggu ini?',
      battleAssignment: 'Apa tugas Swordland saya?',
      transferStatus: 'Apa yang masih kurang untuk transfer?',
      territoryPlan: 'Layout hive mana yang kita gunakan untuk Bear Hunt?',
      allianceCommand: 'Apa yang perlu perhatian petugas?',
      eventReadiness: 'Apakah acara verifikasi berikutnya sudah siap?',
      rallyGaps: 'Apa yang tersisa untuk acara berikutnya?',
      bearHuntHistory: 'Tunjukkan sejarah pertunjukan Bear Hunt kami.',
      progressionFreshness: 'Apa pengamatan gubernur yang sudah usang atau hilang?',
      transferVerification: 'Peserta Transfer mana yang perlu verifikasi?',
      intelligenceChanges: 'Apa yang berubah baru-baru ini?',
      territoryComparison:
        'Bagaimana dengan wilayah yang diamati dibandingkan dengan rencana yang diterbitkan?',
    },
    answers: {
      transferVerificationIncomplete:
        'Ringkasan ini menilai {assessed} dari {total} peserta transfer. Setidaknya {count} perlu diperhatikan; {unassessed} belum dinilai di sini. Buka kesiapan transfer untuk pemeriksaan terkini.',
      help: 'Saya dapat menjawab dari Event yang diizinkan, roster dan RSVP Anda, tugas Anda, panduan dan observasi Alliance, rencana wilayah terbit, data game bersumber, dan status transfer Anda dalam cakupan. Saya tidak memakai pengetahuan KingShot tanpa sumber.',
      unsupported:
        'Saya hanya dapat menjawab dari sumber resmi yang didukung. Saya tidak memakai pengetahuan KingShot umum atau membuat perubahan dari Assistant.',
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
      ownerEventWriteHandoff:
        'Aku tidak bisa membuat perubahan ini dari Asisten. Buka aliran kerja pemilik {event} normal untuk melanjutkan.',
      ownerWriteHandoff:
        'Aku tidak bisa mengubah status {owner} dari Asisten. Fakta yang disetujui saat ini ditampilkan di bawah; buka aliran kerja pemilik untuk melanjutkan.',
      allianceCommandAttention:
        'Ringkasan perwira memiliki {count} item fakta yang membutuhkan perhatian. Sumber-sumber yang diakui digambarkan di bawah ini.',
      allianceCommandAttentionNotAvailable:
        'Tidak ada fakta resmi yang tersedia untuk aliansi ini.',
      eventReadiness:
        'Acara yang diverifikasi berikutnya memiliki {count} item siap (s) pemblokiran. Proyeksi pemiliknya ditunjukkan di bawah ini.',
      eventReadinessNotAvailable:
        'Aku tidak bisa menemukan yang sesuai dengan verifikasi kesiapan acara dalam ringkasan pejabat yang diizinkan Anda.',
      rallyGaps:
        'Acara yang diverifikasi berikutnya memiliki {count} Rally readyiness gap(s). Fakta yang dimiliki Rally ditunjukkan di bawah ini.',
      rallyGapsNotAvailable:
        'Aku tidak bisa menemukan fakta ketersediaan Rally untuk acara yang sesuai yang diverifikasi.',
      bearHuntHistory:
        'Aku menemukan {count} rekaman baru-baru ini yang diizinkan Bear Hunt. Negara-negara bukti yang tercatat dan diterima tetap eksplisit di bawah ini.',
      bearHuntHistoryNotAvailable:
        'Aku tidak bisa menemukan perburuan beruang yang disetujui dengan sejarah yang didukung.',
      progressionFreshness:
        '{count} pengamatan gubernur (s) sudah usang atau hilang dalam proyeksi roster-freshness yang diizinkan.',
      progressionFreshnessNotAvailable:
        'Kesegaran pengamatan gubernur tidak tersedia dalam ringkasan pejabat yang diizinkan.',
      transferVerification:
        '{count} Peserta transfer membutuhkan verifikasi atau terblokir dalam penilaian pemilik.',
      transferVerificationNotAvailable:
        'Tidak ada proyeksi verifikasi transfer yang disetujui saat ini.',
      territoryComparison:
        'Perbandingan Teritori yang diizinkan berisi {count} perbedaan yang diamati. Pengamatan yang hilang dan tidak kompatibel tetap eksplisit di bawah ini.',
      territoryComparisonNotAvailable:
        'Tidak ada perbandingan Teritorial yang diterbitkan yang tersedia.',
      allianceSettings:
        'Pengaturan aliansi saat ini adalah {name}, nama URL {slug}, bahasa {language}, dan zona waktu {timezone}.',
      allianceGovernanceHistory:
        'Aku menemukan {count} peristiwa pemerintahan aliansi yang baru saja disetujui. Ini adalah fakta audit pemilik hanya baca.',
      allianceRosterReconciliation:
        'Observasi daftar yang diterima terakhir memiliki {needsReview} item (s) yang membutuhkan tinjauan dan {matched} yang cocok dengan keanggotaan saat ini.',
      allianceRosterReconciliationNotAvailable:
        'Belum ada pengamatan daftar aliansi yang diterima yang tersedia untuk dikonsolidasi.',
    },
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
    handoffs: {
      openRoster: 'Buka roster Event',
      openOwnerWorkflow: 'Buka aliran kerja pemilik',
    },
    transferRequirements: {
      window_phase: 'Fase jendela transfer',
      transfer_group: 'Grup transfer',
      power_cap: 'Batas power',
      invitation: 'Undangan',
      transfer_passes: 'Transfer Pass',
      in_game_rules: 'Aturan dalam game',
    },
    transferStates: {
      met: 'Terpenuhi',
      unmet: 'Belum terpenuhi',
      unknown: 'Tidak diketahui',
      stale: 'Kedaluwarsa',
      conflicting: 'Bertentangan',
      not_applicable: 'Tidak berlaku',
    },
    requirementValues: 'Aktual: {actual} · Diperlukan: {required}',
  },
  intelligenceChange: {
    title: 'Perubahan intelijen terbaru',
    subtitle:
      'Perubahan bersumber dari riwayat yang diizinkan. Perubahan menjelaskan observasi, bukan niat strategis.',
    empty: 'Tidak ada perubahan intelijen material untuk cakupan ini.',
    source: 'Sumber',
    observed: 'Diamati',
    baseline: 'Dasar',
    current: 'Saat ini',
    previous: 'Sebelumnya',
    viewSource: 'Lihat sumber',
    material: 'Perubahan material',
    attention: 'Perlu verifikasi',
  },
} satisfies MessageCatalogue;

export default messages;
