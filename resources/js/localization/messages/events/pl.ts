import type { MessageCatalogue } from '../../types';

const messages = {
  "events": {
    "scope": {
      "player": "Gracz",
      "alliance": "Sojusz",
      "kingdom": "Królestwo"
    },
    "actions": {
      "save": "Zapisz",
      "cancel": "Anuluj"
    },
    "calendar": {
      "title": "Wydarzenia",
      "create": "Utwórz wydarzenie",
      "agenda": "Agenda",
      "month": "Kalendarz",
      "all": "Wszystkie zakresy",
      "manageable": "Zarządzaj",
      "empty": "Brak wydarzeń pasujących do tego widoku.",
      "previousMonth": "Poprzedni miesiąc",
      "nextMonth": "Następny miesiąc",
      "scopeFilters": "Filtruj wydarzenia według zakresu",
      "viewOptions": "Wybierz widok wydarzeń"
    },
    "create": {
      "title": "Utwórz wydarzenie",
      "back": "Wróć do wydarzeń",
      "noContexts": "Obecnie nie masz uprawnień do tworzenia wydarzenia.",
      "context": "Kontekst wydarzenia",
      "eventType": "Typ wydarzenia",
      "start": "Czas rozpoczęcia",
      "duration": "Czas trwania (minuty)",
      "capacity": "Limit",
      "instructions": "Instrukcje",
      "submit": "Utwórz wydarzenie"
    },
    "show": {
      "back": "Wróć do wydarzeń",
      "manage": "Zarządzaj wydarzeniem",
      "details": "Szczegóły wydarzenia",
      "status": "Status",
      "capacity": "Limit",
      "recurrence": "Powtarzanie",
      "modules": "Moduły operacyjne"
    },
    "manage": {
      "title": "Zarządzaj wydarzeniem",
      "back": "Wróć do wydarzeń",
      "save": "Zapisz wydarzenie",
      "cancel": "Anuluj wydarzenie"
    },
    "attention": {
      "title": "Akcje wydarzenia",
      "response": "Wymagana odpowiedź",
      "registration": "Rejestracja dostępna",
      "vote": "Wymagany głos",
      "roster_confirmation": "Wymagane potwierdzenie składu"
    },
    "reminders": {
      "title": "Ostatnie przypomnienia"
    },
    "participation": {
      "register": "Zarejestruj",
      "cancelRegistration": "Anuluj rejestrację"
    },
    "responses": {
      "going": "Idę",
      "maybe": "Może",
      "unavailable": "Niedostępny"
    },
    "registration": {
      "registered": "Zarejestrowany",
      "waitlisted": "Lista oczekujących",
      "cancelled": "Anulowany"
    },
    "scheduleSources": {
      "alliance_controlled": "Kontrolowane przez sojusz",
      "game_calendar": "Kalendarz gry",
      "matchmaking": "Dobieranie",
      "manual": "Ręcznie"
    },
    "recurrencePolicies": {
      "disabled": "Bez powtarzania",
      "fixed_interval": "Stały odstęp",
      "configurable": "Konfigurowalne"
    },
    "recurrenceFrequencies": {
      "none": "Bez powtarzania",
      "daily": "Codziennie",
      "weekly": "Co tydzień"
    },
    "attendanceStatuses": {
      "present": "Obecny",
      "absent": "Nieobecny",
      "excused": "Usprawiedliwiony",
      "unknown": "Nieznany"
    },
    "eventStatuses": {
      "draft": "Szkic",
      "published": "Opublikowane",
      "cancelled": "Anulowane",
      "completed": "Zakończone"
    },
    "capabilities": {
      "responses": "Odpowiedzi",
      "registration": "Rejestracja",
      "waitlist": "Lista oczekujących",
      "attendance": "Obecność",
      "phases": "Fazy",
      "polls": "Głosowania",
      "rosters": "Składy",
      "substitutes": "Rezerwowi",
      "teams": "Drużyny",
      "legions": "Legiony",
      "rally_guidance": "Wskazówki rajdu",
      "formations": "Formacje",
      "objectives": "Cele",
      "scoring": "Punktacja",
      "results": "Wyniki"
    },
    "reminderAudiences": {
      "target": "Cel wydarzenia",
      "responded": "Gracze, którzy odpowiedzieli",
      "registered": "Zarejestrowani gracze",
      "rostered": "Gracze w składzie",
      "all_scope_players": "Wszyscy uprawnieni gracze"
    }
  }
} satisfies MessageCatalogue;

export default messages;
