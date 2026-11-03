import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Assistent',
    title: 'Frag deine Allianz',
    eyebrow: 'Allianz-Assistent · Autorisierte Antworten',
    subtitle:
      'Frage nach Events, deinem Roster und deinen Zusagen, deinen Aufgaben, Allianz-Guides und Beobachtungen, veröffentlichten Gebietsplänen, belegten Spieldaten und deiner Transferbereitschaft, wenn du im gültigen Bereich bist.',
    authorizationHint: 'Antworten verwenden nur Daten, die du sehen darfst.',
    tryAsking: 'Probier eine Frage',
    conversation: 'Unterhaltung mit dem Allianz-Assistenten',
    youAsked: 'Du hast gefragt',
    possibleEvents: 'Mögliche Events',
    openEvent: 'Event öffnen',
    sourcesHeading: 'Verwendete Quellen',
    sourceTime: 'Quellenzeit: {time}',
    questionLabel: 'Frag deine Allianz',
    questionPlaceholder: 'Wann ist Swordland und bin ich im Roster?',
    inputHint: '{count}/{max} Zeichen · Enter zum Fragen · Umschalt+Enter für neue Zeile',
    asking: 'Quellen werden geprüft…',
    ask: 'Fragen',
    notRecorded: 'Nicht erfasst',
    classifications: {
      operational_fact: 'Operative Tatsache',
      game_fact: 'Spieldaten',
      alliance_strategy: 'Allianzstrategie',
      observation: 'Beobachtung',
      alliance_command: 'Übersicht der Beamten',
      event_readiness: 'Veranstaltungsbereitschaft',
      bear_hunt_run: 'Bärenjagd laufen',
      roster_freshness: 'Gouverneur Beobachtung Frische',
      transfer_verification: 'Überprüfung der Übertragung',
      territory_comparison: 'Territorialvergleich',
    },
    sources: {
      event: 'Ereignis',
      roster: 'Mitgliederliste',
      alliance_content: 'Allianz-Guide',
      observation: 'Beobachtung',
      game_fact: 'Spieldaten',
      participation: 'Teilnahme',
      battle_plan_assignment: 'Gefechtsplan-Aufgabe',
      transfer_assessment: 'Transferbewertung',
      territory_plan_revision: 'Veröffentlichter Gebietsplan',
      alliance_command: 'Übersicht der Beamten',
      event_readiness: 'Veranstaltungsbereitschaft',
      bear_hunt_run: 'Bärenjagd laufen',
      roster_freshness: 'Gouverneur Beobachtung Frische',
      transfer_verification: 'Überprüfung der Übertragung',
      territory_comparison: 'Territorialvergleich',
      alliance_settings: 'Allianz Einstellungen',
      alliance_governance_history: 'Geschichte der Allianz-Governance',
      alliance_roster_reconciliation: 'Allianz-Roster-Abstimmung',
    },
    prompts: {
      swordland: 'Wann ist Swordland und bin ich im Roster?',
      nextEvent: 'Was ist mein nächstes Event?',
      bearGuide: 'Was sagt unser Bear-Hunt-Guide?',
      observation: 'Was haben wir über unseren Gegner beobachtet?',
      heroFact: 'Welche Generation ist Amadeus?',
      rsvpWeek: 'Wofür habe ich diese Woche zugesagt?',
      battleAssignment: 'Was ist meine Swordland-Aufgabe?',
      transferStatus: 'Was fehlt mir für den Transfer?',
      territoryPlan: 'Welches Hive-Layout nutzen wir für Bear Hunt?',
      allianceCommand: 'Was braucht die Aufmerksamkeit des Beamten?',
      eventReadiness: 'Ist unser nächstes verifiziertes Event fertig?',
      rallyGaps: 'Welche Rally Lücken bleiben für die nächste Veranstaltung?',
      bearHuntHistory: 'Zeigen Sie unseren jüngsten Bear Hunt Performance-Verlauf.',
      progressionFreshness: 'Welche Beobachtungen des Gouverneurs sind veraltet oder fehlen?',
      transferVerification: 'Welche Transfer-Teilnehmer müssen verifiziert werden?',
      intelligenceChanges: 'Welche materiellen Intelligenz hat sich in letzter Zeit geändert?',
      territoryComparison:
        'Wie verhält sich das beobachtete Gebiet im Vergleich zum veröffentlichten Plan?',
    },
    answers: {
      transferVerificationIncomplete:
        'Diese Übersicht hat {assessed} von {total} Transferteilnehmern bewertet. Mindestens {count} benötigen Aufmerksamkeit; {unassessed} sind hier nicht bewertet. Öffne die Transfervorbereitung für aktuelle Prüfungen.',
      help: 'Ich kann aus autorisierten Events, deinem Roster und deinen Zusagen, deinen Aufgaben, Allianz-Guides und Beobachtungen, veröffentlichten Gebietsplänen, belegten Spieldaten und deinem Transferstatus im gültigen Bereich antworten. Ich nutze kein unbelegtes KingShot-Wissen.',
      unsupported:
        'Ich kann nur aus unterstützten autorisierten Quellen antworten. Ich nutze kein allgemeines KingShot-Wissen und ändere hier nichts.',
      unavailable:
        'Der Allianz-Assistent kann seine Quellen gerade nicht prüfen. Versuche es erneut.',
      rateLimited: 'Du fragst zu schnell. Versuche es gleich noch einmal.',
      validationError: 'Gib eine Frage mit 2 bis {max} Zeichen ein.',
      noUpcomingEvent: 'Ich konnte kein bevorstehendes Event finden, das du sehen darfst.',
      eventSubjectMissing: 'Nenne das Event, das ich prüfen soll.',
      eventNotFound:
        'Ich konnte kein autorisiertes bevorstehendes Event finden, das „{subject}“ entspricht.',
      eventAmbiguous:
        'Ich habe mehrere Events gefunden, die „{subject}“ entsprechen. Öffne unten das gewünschte Event.',
      eventTime: '{event} beginnt {startsAt}.',
      eventTimeNotRostered: '{event} beginnt {startsAt}. Du bist derzeit nicht im Roster.',
      notRostered: 'Du bist derzeit nicht für {event} eingetragen.',
      eventTimeRostered:
        '{event} beginnt {startsAt}. Du bist in {roster}. Rolle: {role}; Platz: {slot}; Status: {status}.',
      rostered:
        'Du bist für {event} in {roster} eingetragen. Rolle: {role}; Platz: {slot}; Status: {status}.',
      contentSubjectMissing: 'Nenne das Event oder Thema, dessen Allianz-Guide ich prüfen soll.',
      contentNotFound:
        'Ich konnte keinen veröffentlichten Allianz-Inhalt finden, der „{subject}“ entspricht.',
      contentFound: 'Allianzstrategie — {title}: {excerpt}',
      observationSubjectMissing:
        'Nenne die Allianz oder das Beobachtungsthema, das ich prüfen soll.',
      observationNotFound:
        'Ich konnte keine autorisierte Beobachtung finden, die „{subject}“ entspricht.',
      observationFound: 'Beobachtung — {title}: {observation}',
      gameFactKnown:
        'Spieldaten — {title}. Die belegten Werte unten stammen aus Datensatz {datasetVersion}.',
      gameFactUnknown:
        'Spieldaten — für {title} liegt kein unterstützter bekannter Wert vor. Ich rate nicht.',
      gameFactConflicting:
        'Spieldaten — die gepflegten Nachweise für {title} widersprechen sich. Ich wähle keinen Wert für dich aus.',
      participationNone: 'Für {event} ist keine Zusage oder Registrierung von dir erfasst.',
      participationFound: 'Deine Teilnahme für {event} steht unten.',
      participationWeekNone: 'Diese Woche wurde keine Zusage oder Registrierung von dir gefunden.',
      participationNotFound:
        'Ich habe keinen passenden Zusage-, Registrierungs- oder Wartelistenstatus von dir gefunden.',
      participationWeek:
        'Ich habe {count} Teilnahme-Eintrag/-Einträge von dir für diese Woche gefunden.',
      participationList: 'Ich habe {count} passende Teilnahme-Einträge von dir gefunden.',
      battlePlanNotFound:
        'Ich konnte kein kommendes autorisiertes Event mit einer Aufgabe für dich finden.',
      battlePlanNone: 'Du hast derzeit keine Gefechtsplan-Aufgabe für {event}.',
      battlePlanOne: 'Du hast eine Gefechtsplan-Aufgabe für {event}.',
      battlePlanMany: 'Du hast {count} Gefechtsplan-Aufgaben für {event}.',
      transferNotInScope:
        'Aus deinem aktuellen autorisierten Transferbereich kann ich keine Transferbereitschaft liefern.',
      transferStatus:
        'Deine Transferbewertung lautet {outcome}. Die vom Eigentümer berechneten Anforderungen stehen unten.',
      territoryPlanNotFound: 'An {event} ist keine veröffentlichte Gebietsplan-Revision angehängt.',
      territoryPlanAmbiguous:
        'An {event} sind mehrere veröffentlichte Gebietsplan-Revisionen angehängt; ich wähle nicht stillschweigend eine aus.',
      territoryPlanFound: '{event} verwendet {planName}, Revision {revisionNumber}, für {purpose}.',
      rosterWriteHandoff:
        'Ich kann das Roster hier nicht ändern. Öffne den normalen Ablauf für {event}, um die Änderung vorzunehmen.',
      ownerEventWriteHandoff:
        'Ich kann diese Änderung nicht über den Assistenten vornehmen. Öffnen Sie den normalen {event}-Besitzer-Workflow, um fortzufahren.',
      ownerWriteHandoff:
        'Ich kann den Status {owner} nicht über den Assistenten ändern. Die aktuellen autorisierten Fakten sind unten aufgeführt. Öffnen Sie den Eigentümer-Workflow, um fortzufahren.',
      allianceCommandAttention:
        'Die Offiziersübersicht hat {count} sachliche Elemente, die Aufmerksamkeit erfordern. Die autorisierten Quellen sind unten aufgeführt.',
      allianceCommandAttentionNotAvailable:
        'Für diese Allianz sind keine offiziellen Fakten zur Übersicht verfügbar.',
      eventReadiness:
        'Das nächste verifizierte Ereignis hat {count} blockierende Bereitschaftselemente. Die Eigentümerprojektion ist unten gezeigt.',
      eventReadinessNotAvailable:
        'Ich konnte keine übereinstimmende verifizierte Veranstaltungsbereitschaft in Ihrer Übersicht der autorisierten Beamten finden.',
      rallyGaps:
        'Das nächste verifizierte Event hat {count} Rallye-Bereitschaftslücke(n). Die Rallye-eigenen Fakten sind unten gezeigt.',
      rallyGapsNotAvailable:
        'Ich konnte keine Rallye-Bereitschaftsdaten für ein passendes verifiziertes Event finden.',
      bearHuntHistory:
        'Ich habe {count} aktuelle autorisierte Bear Hunt Run-Datenbank(en) gefunden. Aufgezeichnete und akzeptierte Evidenzzustände bleiben unten explizit.',
      bearHuntHistoryNotAvailable:
        'Ich konnte keinen autorisierten abgeschlossenen Bear Hunt-Lauf mit unterstützter Historie finden.',
      progressionFreshness:
        '{count} Die Beobachtung(en) des Gouverneurs sind veraltet oder fehlen in der autorisierten Projektion der Roster-Frischheitsdaten.',
      progressionFreshnessNotAvailable:
        'Die Beobachtungsfrische des Gouverneurs ist in Ihrer Prokuristenübersicht nicht verfügbar.',
      transferVerification:
        '{count} Transferteilnehmer müssen verifiziert werden oder sind in der Besitzerbewertung gesperrt.',
      transferVerificationNotAvailable:
        'Es gibt keine aktuelle autorisierte Übertragungs-Verifizierungsprojektion.',
      territoryComparison:
        'Der autorisierte Territorium-Vergleich enthält {count} beobachtete Unterschiede. Fehlende und inkompatible Beobachtungen bleiben unten explizit.',
      territoryComparisonNotAvailable:
        'Es gibt keinen autorisierten veröffentlichten Territoriumsvergleich.',
      allianceSettings:
        'Die aktuellen Allianz-Einstellungen sind {name}, URL-Name {slug}, Sprache {language} und Zeitzone {timezone}.',
      allianceGovernanceHistory:
        'Ich habe {count} aktuelle autorisierte Allianz-Governance-Ereignisse gefunden. Dies sind schreibgeschützte Eigentümer-Audit-Fakten.',
      allianceRosterReconciliation:
        'Die zuletzt akzeptierte Beobachtung des Dienstplans enthält {needsReview} Elemente, die überprüft werden müssen, und {matched}, die der aktuellen Mitgliedschaft entsprechen.',
      allianceRosterReconciliationNotAvailable:
        'Es gibt noch keine akzeptierte Allianz-Roster-Beobachtung, die abgeglichen werden kann.',
    },
    possibleMatches: 'Mögliche Treffer',
    detailsHeading: 'Antwortdetails',
    requirementsHeading: 'Transferanforderungen',
    datasetVersion: 'Datensatzversion',
    evidenceStatus: 'Nachweisstatus',
    checksum: 'Prüfsumme',
    revisionLabel: 'Revision {revision}',
    participationSummary: 'Zusage: {response} · Registrierung: {registration}',
    waitlistPosition: 'Wartelistenplatz: {position}',
    teamLabel: 'Team: {team}',
    handoffs: {
      openRoster: 'Event-Roster öffnen',
      openOwnerWorkflow: 'Eigentümer-Workflow öffnen',
    },
    transferRequirements: {
      window_phase: 'Transferfenster-Phase',
      transfer_group: 'Transfergruppe',
      power_cap: 'Machtlimit',
      invitation: 'Einladung',
      transfer_passes: 'Transferpässe',
      in_game_rules: 'Regeln im Spiel',
    },
    transferStates: {
      met: 'Erfüllt',
      unmet: 'Nicht erfüllt',
      unknown: 'Unbekannt',
      stale: 'Veraltet',
      conflicting: 'Widersprüchlich',
      not_applicable: 'Nicht zutreffend',
    },
    requirementValues: 'Ist: {actual} · Erforderlich: {required}',
  },
  intelligenceChange: {
    title: 'Aktuelle Intelligence-Änderungen',
    subtitle:
      'Quellenbelegte Änderungen aus autorisierter Historie. Sie beschreiben Beobachtungen, keine strategische Absicht.',
    empty: 'Für diesen Bereich sind keine wesentlichen Intelligence-Änderungen verfügbar.',
    source: 'Quelle',
    observed: 'Beobachtet',
    baseline: 'Basis',
    current: 'Aktuell',
    previous: 'Vorher',
    viewSource: 'Quelle ansehen',
    material: 'Wesentliche Änderung',
    attention: 'Prüfung erforderlich',
  },
} satisfies MessageCatalogue;

export default messages;
