import type { MessageCatalogue } from '../../types';

const messages = {
  integrationExperience: {
    recordCount: '{count} Einträge',
    historyUnavailable: 'Diese Seite konnte nicht geladen werden.',
    retryPage: 'Seite erneut laden',
    expired: 'Abgelaufen',
    deliveryQueued: 'Eingereiht',
    deliveryHistory: 'Zustellverlauf',
    eyebrow: 'Allianz-Integrationen',
    title: 'API-Zugangsdaten & Webhooks',
    activeCredentials: 'Aktive Zugangsdaten',
    activeWebhooks: 'Aktive Webhooks',
    recentDeliveries: 'Letzte Zustellungen',
    apiCredentials: 'API-Zugangsdaten',
    createCredential: 'Zugangsdaten erstellen',
    revoke: 'Widerrufen',
    webhookSubscriptions: 'Webhook-Abonnements',
    createWebhook: 'Webhook erstellen',
    deliveryLog: 'Letztes Zustellprotokoll',
    event: 'Ereignis',
    attempts: 'Versuche',
    lastError: 'Letzter Fehler',
    subtitle:
      'Verwalten Sie den Zugriff auf API, signierte Webhooks, Allianzlimits und aktuelle Zustellungsergebnisse für {alliance}.',
    credentialLimit: 'API Anmeldeinformationen-Limit',
    webhookLimit: 'Maximale Anzahl von Webhooks',
    saveCredentialNow: 'Speichern Sie jetzt diese API-Anmeldung',
    credentialOneTime: 'Nach dieser Antwort kann das Token nicht mehr abgerufen werden.',
    saveWebhookNow: 'Speichern Sie jetzt diesen Webhook-Signierschlüssel',
    webhookOneTime:
      'Verwenden Sie dieses Geheimnis, um den Header X-Kingshot-Signature zu überprüfen. Sie kann nicht wieder abgerufen werden.',
    commandApi: 'Bot & API Zugriff',
    botReadyReads: 'Verfügbare Bot-Daten',
    commandApiHelp:
      'Verwenden Sie diese JSON-Endpunkte aus Discord, Telegram oder einer anderen zugelassenen Integration. Jede API-Anmeldeinformation erhält nur die unten ausgewählten Bereiche.',
    commandApiBoundary:
      'Schreibanforderungen erfordern eine autorisierte API-Anmeldeinformation, eine verifizierte Governor-Verbindung und einen Idempotenzschlüssel. Normale Allianz- und Eventberechtigungen gelten weiterhin.',
    scopeAlliance: 'Allianzidentität und öffentliche Einstellungen.',
    scopeEvents: 'Allianz-Ereignisvorkommen.',
    scopeContributions: 'Genehmigte Allianzbeitragsdatensätze.',
    scopeCommands:
      'Übersichtsdaten einschließlich Veranstaltungen, Geschenkcodes, Leitfäden und Rekrutierung Status.',
    scopeGiftCodes: 'Aktive Geschenkcodes und die offizielle Einlöseübergabe.',
    scopeContent: 'Veröffentlichte Allianz-Leitfäden und Quelleninformationen.',
    scopeActorLinks: 'Verwenden Sie einmalig Discord oder Telegram Regler-Pairing-Codes.',
    scopeEventParticipation:
      'Senden Sie Veranstaltungsverfügbarkeit und Registrierungsänderungen für einen verifizierten verknüpften Gouverneur ein.',
    apiEnabled: 'API Zugriff ist aktiviert.',
    apiDisabled: 'Der API-Zugriff ist für diese Allianz deaktiviert.',
    apiLimitHelp: 'Bis zu {limit} aktive API-Anmeldeinformationen sind vom Allianzplan zulässig.',
    creatingCredential: 'Erstellen der API-Anmeldeinformationen…',
    name: 'Name',
    expiresAt: 'Abläuft am',
    optional: 'Optional',
    scopes: 'Geltungsbereiche',
    prefix: 'Präfix',
    lastUsed: 'Zuletzt verwendet',
    never: 'Nie',
    state: 'Status',
    active: 'Aktiv',
    revoked: 'Widerrufen',
    revoking: 'Ich werde widerrufen…',
    revokeTitle: 'API Zugriff widerrufen?',
    revokeDescription:
      'Widerrufen Sie {name} sofort. Anfragen, die diese Methode verwenden, funktionieren nicht mehr.',
    credentialCreated:
      'Die Anmeldeinformation API wurde erstellt. Speichern Sie jetzt das Einmal-Token.',
    credentialRevoked: 'Die Anmeldeinformation API wurde widerrufen.',
    noCredentials: 'Es wurden keine API-Anmeldeinformationen erstellt.',
    webhooksEnabled: 'Webhooks sind aktiviert.',
    webhooksDisabled: 'Webhooks sind für diese Allianz deaktiviert.',
    webhookLimitHelp:
      'Bis zu {limit} aktive Webhooks sind erlaubt. Eine Liefer-URL ist erforderlich.',
    creatingWebhook: 'Webhook wird hinzugefügt…',
    webhookCreated:
      'Der Webhook wurde erstellt. Speichern Sie jetzt den einmaligen Signaturschlüssel.',
    webhookRevoked: 'Der Webhook wurde widerrufen.',
    webhookTestQueued:
      'Eine signierte Testzustellung wurde für die ausgewählte URL in die Warteschlange gelegt.',
    webhookDeliveryRetried:
      'Die fehlgeschlagene Zustellung wurde für einen weiteren Versuch in die Warteschlange gesetzt.',
    operationFailed: 'Die Verbindungsaktion konnte nicht abgeschlossen werden.',
    httpsEndpoint: 'Liefer-URL',
    events: 'Veranstaltungen',
    eventsHelp:
      'Wählen Sie ein oder mehrere unterstützte Webhook-Events aus oder wählen Sie * für den vollständigen Katalog.',
    eventAllDescription:
      'Erhalten Sie alle aktuellen und zukünftigen Ereignisse im öffentlichen Webhook-Katalog.',
    eventContentPublished:
      'Eine Allianz-Bekanntmachung oder eine Allianz-Mitteilung wird veröffentlicht.',
    eventCreated: 'Ein Allianz-Event wird erstellt.',
    eventUpdated: 'Ein Allianz-Event-Zeitplan oder Konfigurationsänderungen.',
    eventCancelled: 'Ein Allianz-Event wird abgesagt.',
    eventMemberUpdated: 'Ein Mitgliedsstatus oder Allianzrang ändert sich.',
    eventMemberLeft: 'Ein Gouverneur verlässt die Mitgliedschaft oder die beobachtete Liste.',
    eventCandidateStageChanged: 'Ein Recruitment-Kandidat geht in eine andere Phase.',
    eventCandidateJoined: 'Ein akzeptierter Rekrutierungsanwärter tritt der Allianz bei.',
    eventGiftCodeCreated: 'Ein neuer Geschenkcode wird in die gemeinsame Liste aufgenommen.',
    eventGiftCodeProvenanceAdded:
      'Neue Quelleninformationen werden an einen bestehenden Geschenkcode angehängt.',
    eventGiftCodeStatusChanged:
      'Der Status des Geschenkcodes ändert sich nach Ablauf oder Nachweis des Gouverneurs.',
    eventGiftCodeExpiryChanged: 'Eine qualifizierte Geschenkcode-Ablaufänderung.',
    eventBroadcastScheduleUpdated:
      'Eine wiederkehrende Allianz-Benachrichtigungsregel wird erstellt oder geändert.',
    eventBroadcastScheduleCancelled:
      'Eine wiederkehrende Allianz-Benachrichtigungsregel wird storniert.',
    eventBroadcastRunQueued:
      'Eine geplante Allianz-Benachrichtigung wird für ihre Empfänger in die Warteschlange gelegt.',
    eventBroadcastDeliverySucceeded:
      'Ein externer Allianz-Benachrichtigungskanal bestätigt die Zustellung.',
    eventBroadcastDeliveryFailed:
      'Ein externer Allianz-Benachrichtigungszustellungsversuch schlägt fehl.',
    noWebhooks: 'Es wurden keine Webhooks erstellt.',
    sendTest: 'Signierten Test senden',
    sendingTest: 'Test wird gesendet…',
    rotateSecret: 'Signierschlüssel drehen',
    rotatingSecret: 'Drehbarer Schlüssel…',
    rotateSecretTitle: 'Diesen Signierschlüssel drehen?',
    rotateSecretDescription:
      'Ersetzen Sie den Signaturschlüssel für {name} sofort. Aktualisieren Sie den Empfänger vor der nächsten Lieferung.',
    secretRotated: 'Unterschriftenschlüssel gedreht',
    deliveryHelp:
      'Zuletzt aufgezeichnete Webhook-Zustellungsversuche. Das Öffnen dieser Historie sendet sie nicht erneut.',
    status: 'Status',
    responseCode: 'HTTP',
    lastAttempt: 'Der letzte Versuch',
    deliveredAt: 'Zugestellt',
    noError: 'Kein aufgezeichneter Fehler',
    noDeliveries: 'Es wurden keine Webhook-Zustellungsversuche aufgezeichnet.',
    retryDelivery: 'Versuchen Sie die Lieferung erneut',
    retryingDelivery: 'Wiederholung in der Warteschlange…',
    actions: 'Aktionen',
    deliveryPending: 'Ausstehend',
    deliveryDelivering: 'Wird zugestellt',
    deliveryDelivered: 'Zugestellt',
    deliveryFailed: 'Fehlgeschlagen',
    deliveryUnknown: 'Unbekannt',
    enabled: 'Aktiviert',
    disabled: 'Deaktiviert',
  },
} satisfies MessageCatalogue;

export default messages;
