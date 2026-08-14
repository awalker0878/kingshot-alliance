import type { MessageCatalogue } from '../../types';

const messages = {
  "events": {
    "scope": {
      "player": "Spieler",
      "alliance": "Allianz",
      "kingdom": "Königreich"
    },
    "actions": {
      "save": "Speichern",
      "cancel": "Abbrechen"
    },
    "calendar": {
      "title": "Events",
      "create": "Event erstellen",
      "agenda": "Agenda",
      "month": "Kalender",
      "all": "Alle Bereiche",
      "manageable": "Verwalten",
      "empty": "Keine Events entsprechen dieser Ansicht.",
      "previousMonth": "Vorheriger Monat",
      "nextMonth": "Nächster Monat",
      "scopeFilters": "Events nach Bereich filtern",
      "viewOptions": "Eventansicht auswählen"
    },
    "create": {
      "title": "Event erstellen",
      "back": "Zurück zu Events",
      "noContexts": "Du hast derzeit keine Berechtigung, ein Event zu erstellen.",
      "context": "Eventkontext",
      "eventType": "Eventtyp",
      "start": "Startzeit",
      "duration": "Dauer (Minuten)",
      "capacity": "Kapazität",
      "instructions": "Anweisungen",
      "submit": "Event erstellen"
    },
    "show": {
      "back": "Zurück zu Events",
      "manage": "Event verwalten",
      "details": "Eventdetails",
      "status": "Status",
      "capacity": "Kapazität",
      "recurrence": "Wiederholung",
      "modules": "Betriebsmodule"
    },
    "manage": {
      "title": "Event verwalten",
      "back": "Zurück zu Events",
      "save": "Event speichern",
      "cancel": "Event absagen"
    },
    "attention": {
      "title": "Eventaktionen",
      "response": "Antwort erforderlich",
      "registration": "Registrierung verfügbar",
      "vote": "Abstimmung erforderlich",
      "roster_confirmation": "Roster-Bestätigung erforderlich"
    },
    "reminders": {
      "title": "Aktuelle Erinnerungen"
    },
    "participation": {
      "register": "Registrieren",
      "cancelRegistration": "Registrierung stornieren"
    },
    "responses": {
      "going": "Dabei",
      "maybe": "Vielleicht",
      "unavailable": "Nicht verfügbar"
    },
    "registration": {
      "registered": "Registriert",
      "waitlisted": "Warteliste",
      "cancelled": "Storniert"
    },
    "scheduleSources": {
      "alliance_controlled": "Allianzgesteuert",
      "game_calendar": "Spielkalender",
      "matchmaking": "Matchmaking",
      "manual": "Manuell"
    },
    "recurrencePolicies": {
      "disabled": "Keine Wiederholung",
      "fixed_interval": "Festes Intervall",
      "configurable": "Konfigurierbar"
    },
    "recurrenceFrequencies": {
      "none": "Keine Wiederholung",
      "daily": "Täglich",
      "weekly": "Wöchentlich"
    },
    "attendanceStatuses": {
      "present": "Anwesend",
      "absent": "Abwesend",
      "excused": "Entschuldigt",
      "unknown": "Unbekannt"
    },
    "eventStatuses": {
      "draft": "Entwurf",
      "published": "Veröffentlicht",
      "cancelled": "Abgesagt",
      "completed": "Abgeschlossen"
    },
    "capabilities": {
      "responses": "Antworten",
      "registration": "Registrierung",
      "waitlist": "Warteliste",
      "attendance": "Anwesenheit",
      "phases": "Phasen",
      "polls": "Abstimmungen",
      "rosters": "Roster",
      "substitutes": "Ersatzspieler",
      "teams": "Teams",
      "legions": "Legionen",
      "rally_guidance": "Rallye-Leitfaden",
      "formations": "Formationen",
      "objectives": "Ziele",
      "scoring": "Punkte",
      "results": "Ergebnisse"
    },
    "reminderAudiences": {
      "target": "Eventziel",
      "responded": "Spieler mit Antwort",
      "registered": "Registrierte Spieler",
      "rostered": "Eingeteilte Spieler",
      "all_scope_players": "Alle berechtigten Spieler"
    }
  }
} satisfies MessageCatalogue;

export default messages;
