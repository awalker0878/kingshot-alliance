import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    recordCount: '{count} record',
    historyUnavailable: 'Impossibile caricare questa pagina.',
    retryPage: 'Riprova pagina',
    title: 'Amministrazione della piattaforma',
    backDashboard: 'Torna alla Home',
    localizationRuntime: 'Lingue',
  },
} satisfies MessageCatalogue;

export default messages;
