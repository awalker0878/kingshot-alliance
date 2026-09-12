import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Cari pilihan',
    choicePageSummary:
      '{count} pilihan pada halaman ini; {total} cocok. Diurutkan menurut ID rekaman tetap.',
    choiceUnavailable: 'Pilihan yang dipilih tidak lagi tersedia.',
    choicesFailed:
      'Pilihan tidak dapat dimuat. Pilihan Anda dan halaman yang tampil tidak berubah.',
    retryChoices: 'Coba lagi',

    participantPagination: 'Halaman peserta',
    participantPageSummary: '{count} peserta di halaman ini; {total} dalam tampilan ini.',
    participantPageOrder:
      'Diurutkan menurut ID pendaftaran tetap. Total mencakup seluruh tampilan.',
    participantPageUnavailable:
      'Halaman peserta tidak dapat dimuat. Halaman yang ditampilkan tidak berubah.',
    retryParticipantPage: 'Coba lagi',
    participantFilterPageOnly:
      'Filter kelayakan ini berlaku untuk halaman saat ini. Lanjutkan ke halaman berikutnya untuk meninjau semua peserta.',
    workflowHistoryUnavailable: 'Riwayat alur transfer tidak dapat dimuat.',
    workflowHistoryTotal: '{count} catatan dalam riwayat ini.',
    observationHistoryUnavailable: 'Riwayat pengamatan tidak dapat dimuat.',
    reloadHistory: 'Muat ulang riwayat',
    eyebrow: 'Transfer Kerajaan',
    title: 'Perencanaan transfer',
    readinessBoard: 'Kesiapan',
    completion: 'Hasil',
    manageTransfers: 'Kelola transfer',
    currentCycle: 'Siklus saat ini',
    participants: 'Peserta',
    incoming: 'Masuk',
    outgoing: 'Keluar',
    staying: 'Tetap',
    transferGroups: 'Grup transfer',
    player: 'Gubernur',
    gamePlayerId: 'ID game Gubernur',
    readinessTitle: 'Kesiapan transfer',
    completionTitle: 'Hasil transfer',
    recordCompletion: 'Catat hasil transfer',
    rosterHandoffRecorded: 'Roster aliansi diperbarui',
    completedStatus: 'Selesai',
    notCompletedStatus: 'Belum selesai',
    readinessReady: 'Siap',
    readinessBlocked: 'Terhambat',
    readinessConfirmed: 'Dikonfirmasi',
  },
} satisfies MessageCatalogue;

export default messages;
