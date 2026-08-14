import type { MessageCatalogue } from '../../types';

const messages = {
  "events": {
    "scope": {
      "player": "Joueur",
      "alliance": "Alliance",
      "kingdom": "Royaume"
    },
    "actions": {
      "save": "Enregistrer",
      "cancel": "Annuler"
    },
    "calendar": {
      "title": "Événements",
      "create": "Créer un événement",
      "agenda": "Agenda",
      "month": "Calendrier",
      "all": "Tous les périmètres",
      "manageable": "Gérer",
      "empty": "Aucun événement ne correspond à cette vue.",
      "previousMonth": "Mois précédent",
      "nextMonth": "Mois suivant",
      "scopeFilters": "Filtrer les événements par périmètre",
      "viewOptions": "Choisir la vue des événements"
    },
    "create": {
      "title": "Créer un événement",
      "back": "Retour aux événements",
      "noContexts": "Vous n’avez actuellement pas l’autorisation de créer un événement.",
      "context": "Contexte de l’événement",
      "eventType": "Type d’événement",
      "start": "Heure de début",
      "duration": "Durée (minutes)",
      "capacity": "Capacité",
      "instructions": "Instructions",
      "submit": "Créer un événement"
    },
    "show": {
      "back": "Retour aux événements",
      "manage": "Gérer l’événement",
      "details": "Détails de l’événement",
      "status": "Statut",
      "capacity": "Capacité",
      "recurrence": "Récurrence",
      "modules": "Modules opérationnels"
    },
    "manage": {
      "title": "Gérer l’événement",
      "back": "Retour aux événements",
      "save": "Enregistrer l’événement",
      "cancel": "Annuler l’événement"
    },
    "attention": {
      "title": "Actions de l’événement",
      "response": "Réponse requise",
      "registration": "Inscription disponible",
      "vote": "Vote requis",
      "roster_confirmation": "Confirmation de roster requise"
    },
    "reminders": {
      "title": "Rappels récents"
    },
    "participation": {
      "register": "S’inscrire",
      "cancelRegistration": "Annuler l’inscription"
    },
    "responses": {
      "going": "Présent",
      "maybe": "Peut-être",
      "unavailable": "Indisponible"
    },
    "registration": {
      "registered": "Inscrit",
      "waitlisted": "Liste d’attente",
      "cancelled": "Annulé"
    },
    "scheduleSources": {
      "alliance_controlled": "Contrôlé par l’alliance",
      "game_calendar": "Calendrier du jeu",
      "matchmaking": "Appariement",
      "manual": "Manuel"
    },
    "recurrencePolicies": {
      "disabled": "Sans récurrence",
      "fixed_interval": "Intervalle fixe",
      "configurable": "Configurable"
    },
    "recurrenceFrequencies": {
      "none": "Sans récurrence",
      "daily": "Quotidien",
      "weekly": "Hebdomadaire"
    },
    "attendanceStatuses": {
      "present": "Présent",
      "absent": "Absent",
      "excused": "Excusé",
      "unknown": "Inconnu"
    },
    "eventStatuses": {
      "draft": "Brouillon",
      "published": "Publié",
      "cancelled": "Annulé",
      "completed": "Terminé"
    },
    "capabilities": {
      "responses": "Réponses",
      "registration": "Inscription",
      "waitlist": "Liste d’attente",
      "attendance": "Présence",
      "phases": "Phases",
      "polls": "Sondages",
      "rosters": "Rosters",
      "substitutes": "Remplaçants",
      "teams": "Équipes",
      "legions": "Légions",
      "rally_guidance": "Consignes de rallye",
      "formations": "Formations",
      "objectives": "Objectifs",
      "scoring": "Score",
      "results": "Résultats"
    },
    "reminderAudiences": {
      "target": "Cible de l’événement",
      "responded": "Joueurs ayant répondu",
      "registered": "Joueurs inscrits",
      "rostered": "Joueurs au roster",
      "all_scope_players": "Tous les joueurs éligibles"
    }
  }
} satisfies MessageCatalogue;

export default messages;
