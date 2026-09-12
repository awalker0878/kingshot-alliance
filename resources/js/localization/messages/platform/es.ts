import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
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
