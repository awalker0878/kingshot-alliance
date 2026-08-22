import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Erinnerungen an Positionsvorteile',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Kontosteuerung',
      title: 'Konto & Sicherheit',
      intro:
        'Verwalte Identität, Verifizierung, Passwort, Zwei-Faktor-Authentifizierung und aktive Sitzungen.',
      passwordUpdated:
        'Dein Passwort wurde aktualisiert und andere authentifizierte Sitzungen wurden beendet.',
      sessionsRevoked: 'Andere authentifizierte Sitzungen wurden abgemeldet.',
      twoFactorDisabled: 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.',
      profileTitle: 'Profil',
      profileIntro:
        'Nach einer Änderung der E-Mail-Adresse ist eine erneute Verifizierung erforderlich.',
      timezone: 'Zeitzone',
      saveProfile: 'Profil speichern',
      emailVerification: 'E-Mail-Verifizierung',
      verified: 'Verifiziert',
      pending: 'Ausstehend',
      twoFactorState: 'Zwei-Faktor-Authentifizierung',
      enabled: 'Aktiviert',
      setupPending: 'Einrichtung ausstehend',
      notEnabled: 'Nicht aktiviert',
      twoFactorTitle: 'Zwei-Faktor-Authentifizierung',
      twoFactorIntro:
        'Schütze die Anmeldung mit einer Authenticator-App. Wiederherstellungscodes werden nur bei Erstellung oder Neugenerierung angezeigt.',
      startSetup: 'Einrichtung starten',
      authenticatorSecret: 'Authenticator-Geheimnis',
      provisioningUri: 'Bereitstellungs-URI',
      authenticationCode: 'Authentifizierungscode',
      confirm: 'Bestätigen',
      saveRecoveryCodes: 'Diese Wiederherstellungscodes jetzt speichern',
      recoveryIntro: 'Jeder Code funktioniert einmal. Bewahre sie getrennt von diesem Konto auf.',
      regenerateRecoveryCodes: 'Wiederherstellungscodes neu erzeugen',
      disableTwoFactor: 'Zwei-Faktor-Authentifizierung deaktivieren',
      passwordTitle: 'Passwort ändern',
      passwordIntro:
        'Eine Passwortänderung meldet andere Geräte ab und beendet andere aktive Zugriffe.',
      currentPassword: 'Aktuelles Passwort',
      newPassword: 'Neues Passwort',
      confirmNewPassword: 'Neues Passwort bestätigen',
      updatePassword: 'Passwort aktualisieren',
      sessionsTitle: 'Andere Sitzungen',
      sessionsIntro: 'Beende alle authentifizierten Sitzungen außer diesem Gerät.',
      signOutOthers: 'Andere Geräte abmelden',
      dangerTitle: 'Gefahrenbereich',
      deleteAccount: 'Konto löschen',
    },
    deletion: {
      eyebrow: 'Kontolebenszyklus',
      title: 'Konto löschen',
      intro:
        'Für die Löschung gilt eine siebentägige Wartefrist. Aktiver Allianzbesitz, Plattformadministrator-Zugriff oder rechtliche Sperren können die Verarbeitung blockieren. Verarbeitete Konten werden anonymisiert, statt die Audit-Historie zu entfernen.',
      currentRequest: 'Aktuelle Anfrage',
      eligibleAt: 'Berechtigt ab',
      requestedAt: 'Angefordert',
      processedAt: 'Verarbeitet',
      notYet: 'Noch nicht',
      requestTitle: 'Löschung anfordern',
      requestIntro:
        'Übertrage zuerst die Eigentümerschaft aller Allianzen. Rechtlich gesperrte oder für Sicherheit und Audit notwendige Datensätze bleiben pseudonymisiert erhalten.',
      requestButton: 'Kontolöschung anfordern',
      confirm:
        'Kontolöschung anfordern? Es gilt eine siebentägige Wartefrist sowie Eigentums- und Legal-Hold-Prüfungen.',
      requested: 'Deine Kontolöschungsanfrage wurde erfasst.',
      backToAccount: 'Zurück zu Konto & Sicherheit',
    },
  },
} satisfies MessageCatalogue;

export default messages;
