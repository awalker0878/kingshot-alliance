import type { MessageCatalogue } from '../../types';

const messages = {
  "events": {
    "scope": {
      "player": "Pemain",
      "alliance": "Aliansi",
      "kingdom": "Kerajaan"
    },
    "actions": {
      "save": "Simpan",
      "cancel": "Batal"
    },
    "calendar": {
      "title": "Event",
      "create": "Buat event",
      "agenda": "Agenda",
      "month": "Kalender",
      "all": "Semua cakupan",
      "manageable": "Kelola",
      "empty": "Tidak ada event yang cocok dengan tampilan ini.",
      "previousMonth": "Bulan sebelumnya",
      "nextMonth": "Bulan berikutnya",
      "scopeFilters": "Filter event berdasarkan cakupan",
      "viewOptions": "Pilih tampilan event"
    },
    "create": {
      "title": "Buat event",
      "back": "Kembali ke event",
      "noContexts": "Saat ini Anda tidak memiliki izin untuk membuat event.",
      "context": "Konteks event",
      "eventType": "Jenis event",
      "start": "Waktu mulai",
      "duration": "Durasi (menit)",
      "capacity": "Kapasitas",
      "instructions": "Petunjuk",
      "submit": "Buat event"
    },
    "show": {
      "back": "Kembali ke event",
      "manage": "Kelola event",
      "details": "Detail event",
      "status": "Status",
      "capacity": "Kapasitas",
      "recurrence": "Pengulangan",
      "modules": "Modul operasional"
    },
    "manage": {
      "title": "Kelola event",
      "back": "Kembali ke event",
      "save": "Simpan event",
      "cancel": "Batalkan event"
    },
    "attention": {
      "title": "Tindakan event",
      "response": "Perlu respons",
      "registration": "Pendaftaran tersedia",
      "vote": "Perlu suara",
      "roster_confirmation": "Perlu konfirmasi roster"
    },
    "reminders": {
      "title": "Pengingat terbaru"
    },
    "participation": {
      "register": "Daftar",
      "cancelRegistration": "Batalkan pendaftaran"
    },
    "responses": {
      "going": "Ikut",
      "maybe": "Mungkin",
      "unavailable": "Tidak tersedia"
    },
    "registration": {
      "registered": "Terdaftar",
      "waitlisted": "Daftar tunggu",
      "cancelled": "Dibatalkan"
    },
    "scheduleSources": {
      "alliance_controlled": "Dikendalikan aliansi",
      "game_calendar": "Kalender game",
      "matchmaking": "Matchmaking",
      "manual": "Manual"
    },
    "recurrencePolicies": {
      "disabled": "Tanpa pengulangan",
      "fixed_interval": "Interval tetap",
      "configurable": "Dapat dikonfigurasi"
    },
    "recurrenceFrequencies": {
      "none": "Tanpa pengulangan",
      "daily": "Harian",
      "weekly": "Mingguan"
    },
    "attendanceStatuses": {
      "present": "Hadir",
      "absent": "Tidak hadir",
      "excused": "Berhalangan",
      "unknown": "Tidak diketahui"
    },
    "eventStatuses": {
      "draft": "Draf",
      "published": "Dipublikasikan",
      "cancelled": "Dibatalkan",
      "completed": "Selesai"
    },
    "capabilities": {
      "responses": "Respons",
      "registration": "Pendaftaran",
      "waitlist": "Daftar tunggu",
      "attendance": "Kehadiran",
      "phases": "Fase",
      "polls": "Polling",
      "rosters": "Roster",
      "substitutes": "Pengganti",
      "teams": "Tim",
      "legions": "Legiun",
      "rally_guidance": "Panduan rally",
      "formations": "Formasi",
      "objectives": "Objektif",
      "scoring": "Skor",
      "results": "Hasil"
    },
    "reminderAudiences": {
      "target": "Target event",
      "responded": "Pemain yang merespons",
      "registered": "Pemain terdaftar",
      "rostered": "Pemain di roster",
      "all_scope_players": "Semua pemain yang memenuhi syarat"
    }
  }
} satisfies MessageCatalogue;

export default messages;
