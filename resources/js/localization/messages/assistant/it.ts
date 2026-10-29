import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Assistente',
    title: 'Chiedi alla tua Alleanza',
    eyebrow: 'Assistente Alleanza · Risposte autorizzate',
    subtitle:
      'Chiedi di Event, del tuo roster e delle tue RSVP, dei tuoi incarichi, delle guide e osservazioni dell’Alleanza, dei piani territorio pubblicati, dei dati di gioco con fonte e della tua prontezza al trasferimento quando sei nell’ambito.',
    authorizationHint: 'Le risposte usano solo dati che sei autorizzato a vedere.',
    tryAsking: 'Prova a chiedere',
    conversation: 'Conversazione con l’Assistente Alleanza',
    youAsked: 'Hai chiesto',
    possibleEvents: 'Eventi possibili',
    openEvent: 'Apri Evento',
    sourcesHeading: 'Fonti usate',
    sourceTime: 'Data fonte: {time}',
    questionLabel: 'Chiedi alla tua Alleanza',
    questionPlaceholder: 'A che ora è Swordland e sono nel roster?',
    inputHint: '{count}/{max} caratteri · Invio per chiedere · Maiusc+Invio per una nuova riga',
    asking: 'Controllo delle fonti…',
    ask: 'Chiedi',
    notRecorded: 'Non registrato',
    classifications: {
      operational_fact: 'Dato operativo',
      game_fact: 'Dati di gioco',
      alliance_strategy: 'Strategia dell’Alleanza',
      observation: 'Osservazione',
      alliance_command: "Panoramica dell'ufficiale",
      event_readiness: "Preparazione all'evento",
      bear_hunt_run: 'Bear Hunt correre',
      roster_freshness: 'Governatore osservazione freschezza',
      transfer_verification: 'Verifica del trasferimento',
      territory_comparison: 'Confronto dei territori',
    },
    sources: {
      event: 'Evento',
      roster: 'Elenco membri',
      alliance_content: 'Guida dell’Alleanza',
      observation: 'Osservazione',
      game_fact: 'Dati di gioco',
      participation: 'Partecipazione',
      battle_plan_assignment: 'Incarico piano di battaglia',
      transfer_assessment: 'Valutazione trasferimento',
      territory_plan_revision: 'Piano territorio pubblicato',
      alliance_command: "Panoramica dell'ufficiale",
      event_readiness: "Preparazione all'evento",
      bear_hunt_run: 'Bear Hunt correre',
      roster_freshness: 'Governatore osservazione freschezza',
      transfer_verification: 'Verifica del trasferimento',
      territory_comparison: 'Confronto dei territori',
      alliance_settings: "Impostazioni dell'alleanza",
      alliance_governance_history: "Storia della governance dell'alleanza",
      alliance_roster_reconciliation: "Riconciliazione del roster dell'alleanza",
    },
    prompts: {
      swordland: 'A che ora è Swordland e sono nel roster?',
      nextEvent: 'Qual è il mio prossimo Evento?',
      bearGuide: 'Cosa dice la nostra guida Bear Hunt?',
      observation: 'Cosa abbiamo osservato sul nostro avversario?',
      heroFact: 'Di quale generazione è Amadeus?',
      rsvpWeek: 'A cosa ho risposto questa settimana?',
      battleAssignment: 'Qual è il mio incarico Swordland?',
      transferStatus: 'Cosa mi manca per il trasferimento?',
      territoryPlan: 'Quale layout dell’hive usiamo per Bear Hunt?',
      allianceCommand: "Che cosa ha bisogno dell'attenzione dell'ufficiale?",
      eventReadiness: 'Il nostro prossimo evento verificato è pronto?',
      rallyGaps: 'Quali sono i gap di Rally che rimangono per il prossimo evento?',
      bearHuntHistory: 'Mostra la nostra recente storia delle prestazioni Bear Hunt.',
      progressionFreshness: 'Quali osservazioni del governatore sono obsolete o mancanti?',
      transferVerification: 'Quali partecipanti al trasferimento hanno bisogno di verifica?',
      intelligenceChanges: "Quali sono stati i cambiamenti recenti nell'Intelligenza Materiale?",
      territoryComparison: 'Come si confronta il Territorio osservato con il piano pubblicato?',
    },
    answers: {
      transferVerificationIncomplete:
        'Questa panoramica ha valutato {assessed} partecipanti al trasferimento su {total}. Almeno {count} richiedono attenzione; {unassessed} non sono valutati qui. Apri la preparazione al trasferimento per le verifiche attuali.',
      help: 'Posso rispondere da Event autorizzati, dal tuo roster e RSVP, dai tuoi incarichi, dalle guide e osservazioni dell’Alleanza, dai piani territorio pubblicati, dai dati di gioco con fonte e dal tuo stato di trasferimento nell’ambito. Non uso conoscenze KingShot senza fonte.',
      unsupported:
        'Posso rispondere solo da fonti autorizzate supportate. Non uso conoscenze generiche di KingShot e non effettuo modifiche dall’Assistente.',
      unavailable: 'L’Assistente Alleanza non può controllare le fonti in questo momento. Riprova.',
      rateLimited: 'Stai facendo domande troppo rapidamente. Riprova tra poco.',
      validationError: 'Inserisci una domanda tra 2 e {max} caratteri.',
      noUpcomingEvent: 'Non ho trovato un Evento imminente che sei autorizzato a vedere.',
      eventSubjectMissing: 'Indica l’Evento che vuoi controllare.',
      eventNotFound: 'Non ho trovato un Evento imminente autorizzato corrispondente a “{subject}”.',
      eventAmbiguous:
        'Ho trovato più Eventi corrispondenti a “{subject}”. Apri quello desiderato qui sotto.',
      eventTime: '{event} inizia {startsAt}.',
      eventTimeNotRostered: '{event} inizia {startsAt}. Al momento non sei nel roster.',
      notRostered: 'Al momento non sei nel roster di {event}.',
      eventTimeRostered:
        '{event} inizia {startsAt}. Sei in {roster}. Ruolo: {role}; posizione: {slot}; stato: {status}.',
      rostered:
        'Sei nel roster di {event}, in {roster}. Ruolo: {role}; posizione: {slot}; stato: {status}.',
      contentSubjectMissing:
        'Indica l’Evento o l’argomento della guida dell’Alleanza da controllare.',
      contentNotFound:
        'Non ho trovato contenuti pubblicati dell’Alleanza corrispondenti a “{subject}”.',
      contentFound: 'Strategia dell’Alleanza — {title}: {excerpt}',
      observationSubjectMissing: 'Indica l’Alleanza o l’argomento di osservazione da controllare.',
      observationNotFound:
        'Non ho trovato un’osservazione autorizzata corrispondente a “{subject}”.',
      observationFound: 'Osservazione — {title}: {observation}',
      gameFactKnown:
        'Dati di gioco — {title}. I valori con fonte sotto provengono dal dataset {datasetVersion}.',
      gameFactUnknown:
        'Dati di gioco — non ho un valore noto supportato per {title}. Non farò ipotesi.',
      gameFactConflicting:
        'Dati di gioco — le evidenze mantenute per {title} sono in conflitto. Non sceglierò un valore per te.',
      participationNone: 'Non hai RSVP o registrazioni registrate per {event}.',
      participationFound: 'La tua partecipazione a {event} è mostrata sotto.',
      participationWeekNone: 'Non ho trovato RSVP o registrazioni tue questa settimana.',
      participationNotFound:
        'Non ho trovato uno stato RSVP, registrazione o lista d’attesa corrispondente.',
      participationWeek:
        'Ho trovato {count} registrazioni di partecipazione per te questa settimana.',
      participationList: 'Ho trovato {count} registrazioni di partecipazione corrispondenti.',
      battlePlanNotFound: 'Non ho trovato un prossimo Event autorizzato con un incarico per te.',
      battlePlanNone: 'Al momento non hai un incarico di battaglia per {event}.',
      battlePlanOne: 'Hai un incarico di battaglia per {event}.',
      battlePlanMany: 'Hai {count} incarichi di battaglia per {event}.',
      transferNotInScope:
        'Non posso fornire la prontezza al trasferimento dal tuo attuale ambito autorizzato.',
      transferStatus:
        'La tua valutazione di trasferimento è {outcome}. I requisiti calcolati dal proprietario sono mostrati sotto.',
      territoryPlanNotFound:
        'Nessuna revisione pubblicata del piano territorio è collegata a {event}.',
      territoryPlanAmbiguous:
        'Più revisioni pubblicate del piano territorio sono collegate a {event}; non ne sceglierò una in silenzio.',
      territoryPlanFound: '{event} usa {planName}, revisione {revisionNumber}, per {purpose}.',
      rosterWriteHandoff:
        'Non posso modificare il roster da qui. Apri il normale flusso di {event} per effettuare la modifica.',
      ownerEventWriteHandoff:
        "Non riesco ad apportare questa modifica dall'Assistente. Aprire il normale flusso di lavoro del proprietario {event} per continuare.",
      ownerWriteHandoff:
        "Non è possibile modificare lo stato {owner} dall'assistente. I dati autorizzati attuali sono mostrati di seguito; aprire il flusso di lavoro del proprietario per continuare.",
      allianceCommandAttention:
        'La panoramica degli ufficiali ha {count} elementi fattivi che richiedono attenzione. Le fonti autorizzate sono indicate di seguito.',
      allianceCommandAttentionNotAvailable:
        'Non ci sono informazioni generali disponibili per questa alleanza.',
      eventReadiness:
        'Il prossimo evento verificato ha {count} elementi di prontezza di blocco. La sua proiezione proprietaria è mostrata di seguito.',
      eventReadinessNotAvailable:
        "Non sono riuscito a trovare la corrispondenza della preparazione verificata per l'evento nella panoramica degli agenti autorizzati.",
      rallyGaps:
        'Il prossimo evento verificato ha {count} gap di prontezza al rally. I fatti di proprietà del Rally sono mostrati di seguito.',
      rallyGapsNotAvailable:
        'Non sono riuscito a trovare i dati di preparazione al Rally per un evento verificato corrispondente.',
      bearHuntHistory:
        'Ho trovato {count} recenti registrazioni autorizzate di Bear Hunt. Gli stati di evidenza registrati e accettati rimangono espliciti di seguito.',
      bearHuntHistoryNotAvailable:
        "Non sono riuscito a trovare una caccia all'orso autorizzata completata con la cronologia supportata.",
      progressionFreshness:
        '{count} le osservazioni del governatore sono obsolete o mancano nella proiezione autorizzata della freschezza del roster.',
      progressionFreshnessNotAvailable:
        "La frequenza di osservazione del governatore non è disponibile nella panoramica dell'ufficiale autorizzato.",
      transferVerification:
        '{count} I partecipanti al trasferimento devono essere verificati o sono bloccati nella valutazione del proprietario.',
      transferVerificationNotAvailable:
        'Non esiste alcuna proiezione di verifica del trasferimento autorizzata.',
      territoryComparison:
        'Il confronto Territorio autorizzato contiene {count} differenza(e) osservata(e). Le osservazioni mancanti e incompatibili rimangono esplicitate di seguito.',
      territoryComparisonNotAvailable:
        'Non è disponibile alcun confronto autorizzato pubblicato del Territorio.',
      allianceSettings:
        "Le impostazioni correnti dell'alleanza sono {name}, nome URL {slug}, lingua {language} e fuso orario {timezone}.",
      allianceGovernanceHistory:
        'Ho trovato {count} eventi recenti di governance di alleanza autorizzati. Questi sono fatti di audit proprietari di sola lettura.',
      allianceRosterReconciliation:
        "L'ultima osservazione di roster accettata ha {needsReview} elementi da rivedere e {matched} corrispondenti all'attuale appartenenza.",
      allianceRosterReconciliationNotAvailable:
        'Non ci sono ancora osservazioni di roster di alleanze accettate disponibili per la riconciliazione.',
    },
    possibleMatches: 'Possibili corrispondenze',
    detailsHeading: 'Dettagli risposta',
    requirementsHeading: 'Requisiti di trasferimento',
    datasetVersion: 'Versione dataset',
    evidenceStatus: 'Stato evidenza',
    checksum: 'Somma di controllo',
    revisionLabel: 'Revisione {revision}',
    participationSummary: 'RSVP: {response} · Registrazione: {registration}',
    waitlistPosition: 'Posizione in lista d’attesa: {position}',
    teamLabel: 'Squadra: {team}',
    handoffs: {
      openRoster: 'Apri roster Event',
      openOwnerWorkflow: 'Aprire il flusso di lavoro del proprietario',
    },
    transferRequirements: {
      window_phase: 'Fase finestra di trasferimento',
      transfer_group: 'Gruppo di trasferimento',
      power_cap: 'Limite potenza',
      invitation: 'Invito',
      transfer_passes: 'Pass trasferimento',
      in_game_rules: 'Regole in gioco',
    },
    transferStates: {
      met: 'Soddisfatto',
      unmet: 'Non soddisfatto',
      unknown: 'Sconosciuto',
      stale: 'Obsoleto',
      conflicting: 'In conflitto',
      not_applicable: 'Non applicabile',
    },
    requirementValues: 'Attuale: {actual} · Richiesto: {required}',
  },
  intelligenceChange: {
    title: 'Modifiche recenti dell’intelligence',
    subtitle:
      'Modifiche con fonte derivate dalla cronologia autorizzata. Descrivono osservazioni, non intenzioni strategiche.',
    empty: 'Nessuna modifica materiale dell’intelligence è disponibile per questo ambito.',
    source: 'Fonte',
    observed: 'Osservato',
    baseline: 'Riferimento',
    current: 'Attuale',
    previous: 'Precedente',
    viewSource: 'Vedi fonte',
    material: 'Modifica materiale',
    attention: 'Da verificare',
  },
} satisfies MessageCatalogue;

export default messages;
