import type { MessageCatalogue } from '../../types';

const messages = {
  "events": {
    "scope": {
      "player": "Jugador",
      "alliance": "Alianza",
      "kingdom": "Reino"
    },
    "actions": {
      "save": "Guardar",
      "cancel": "Cancelar"
    },
    "calendar": {
      "title": "Eventos",
      "create": "Crear evento",
      "agenda": "Agenda",
      "month": "Calendario",
      "all": "Todos los ámbitos",
      "manageable": "Gestionar",
      "empty": "Ningún evento coincide con esta vista.",
      "previousMonth": "Mes anterior",
      "nextMonth": "Mes siguiente",
      "scopeFilters": "Filtrar eventos por ámbito",
      "viewOptions": "Elegir vista de eventos"
    },
    "create": {
      "title": "Crear evento",
      "back": "Volver a eventos",
      "noContexts": "Actualmente no tienes permiso para crear un evento.",
      "context": "Contexto del evento",
      "eventType": "Tipo de evento",
      "start": "Hora de inicio",
      "duration": "Duración (minutos)",
      "capacity": "Capacidad",
      "instructions": "Instrucciones",
      "submit": "Crear evento"
    },
    "show": {
      "back": "Volver a eventos",
      "manage": "Gestionar evento",
      "details": "Detalles del evento",
      "status": "Estado",
      "capacity": "Capacidad",
      "recurrence": "Recurrencia",
      "modules": "Módulos operativos"
    },
    "manage": {
      "title": "Gestionar evento",
      "back": "Volver a eventos",
      "save": "Guardar evento",
      "cancel": "Cancelar evento"
    },
    "attention": {
      "title": "Acciones del evento",
      "response": "Se requiere respuesta",
      "registration": "Registro disponible",
      "vote": "Se requiere voto",
      "roster_confirmation": "Se requiere confirmación de plantilla"
    },
    "reminders": {
      "title": "Recordatorios recientes"
    },
    "participation": {
      "register": "Registrarse",
      "cancelRegistration": "Cancelar registro"
    },
    "responses": {
      "going": "Voy",
      "maybe": "Quizás",
      "unavailable": "No disponible"
    },
    "registration": {
      "registered": "Registrado",
      "waitlisted": "En lista de espera",
      "cancelled": "Cancelado"
    },
    "scheduleSources": {
      "alliance_controlled": "Controlado por la alianza",
      "game_calendar": "Calendario del juego",
      "matchmaking": "Emparejamiento",
      "manual": "Manual"
    },
    "recurrencePolicies": {
      "disabled": "Sin recurrencia",
      "fixed_interval": "Intervalo fijo",
      "configurable": "Configurable"
    },
    "recurrenceFrequencies": {
      "none": "Sin recurrencia",
      "daily": "Diario",
      "weekly": "Semanal"
    },
    "attendanceStatuses": {
      "present": "Presente",
      "absent": "Ausente",
      "excused": "Justificado",
      "unknown": "Desconocido"
    },
    "eventStatuses": {
      "draft": "Borrador",
      "published": "Publicado",
      "cancelled": "Cancelado",
      "completed": "Completado"
    },
    "capabilities": {
      "responses": "Respuestas",
      "registration": "Registro",
      "waitlist": "Lista de espera",
      "attendance": "Asistencia",
      "phases": "Fases",
      "polls": "Encuestas",
      "rosters": "Plantillas",
      "substitutes": "Suplentes",
      "teams": "Equipos",
      "legions": "Legiones",
      "rally_guidance": "Guía de rally",
      "formations": "Formaciones",
      "objectives": "Objetivos",
      "scoring": "Puntuación",
      "results": "Resultados"
    },
    "reminderAudiences": {
      "target": "Objetivo del evento",
      "responded": "Jugadores que respondieron",
      "registered": "Jugadores registrados",
      "rostered": "Jugadores en plantilla",
      "all_scope_players": "Todos los jugadores elegibles"
    }
  }
} satisfies MessageCatalogue;

export default messages;
