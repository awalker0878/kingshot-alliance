import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'La opción seleccionada ya no está disponible.',
    searchChoices: 'Buscar opciones',
    choicePageSummary:
      '{count} opciones en esta página; {total} coinciden. Ordenadas por identificador estable.',
    choicesFailed:
      'No se pudieron cargar las opciones. Se conservan la selección y la página mostrada.',
    retryChoices: 'Reintentar opciones',
    recoveryFailed: 'No se pudo completar la recuperación. Inténtalo de nuevo.',
    recordCount: '{count} registros',
    historyUnavailable: 'No se pudo cargar esta página.',
    retryPage: 'Reintentar página',
    title: 'Administración de la plataforma',
    backDashboard: 'Volver a Inicio',
    capacityTitle: 'Capacidad y operaciones',
    administrators: 'Administradores de plataforma',
    provisionAlliance: 'Crear Alianza',
    allianceFleet: 'Alianzas',
    legalHolds: 'Protección de registros',
    localizationRuntime: 'Idiomas',
  },
} satisfies MessageCatalogue;

export default messages;
