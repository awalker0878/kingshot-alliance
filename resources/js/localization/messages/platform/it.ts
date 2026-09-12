import type { MessageCatalogue } from '../../types';

const messages = {
  platformAdmin: {
    choiceUnavailable: 'L’opzione selezionata non è più disponibile.',
    searchChoices: 'Cerca opzioni',
    choicePageSummary:
      '{count} opzioni in questa pagina; {total} corrispondenze. Ordine per ID stabile.',
    choicesFailed:
      'Impossibile caricare le opzioni. Selezione e pagina visualizzata restano invariate.',
    retryChoices: 'Riprova opzioni',
    recoveryFailed: 'Impossibile completare il recupero. Riprova.',
    recordCount: '{count} record',
    historyUnavailable: 'Impossibile caricare questa pagina.',
    retryPage: 'Riprova pagina',
    title: 'Amministrazione della piattaforma',
    backDashboard: 'Torna alla Home',
    localizationRuntime: 'Lingue',
  },
} satisfies MessageCatalogue;

export default messages;
