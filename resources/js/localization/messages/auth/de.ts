import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Anmelden',
      email: 'E-Mail',
      password: 'Passwort',
      remember: 'Angemeldet bleiben',
      forgotPassword: 'Passwort vergessen?',
      submit: 'Anmelden',
      createAccount: 'Konto erstellen',
      invitation: 'Du hast eine Einladung?',
    },
    register: {
      title: 'Konto erstellen',
      email: 'E-Mail',
      password: 'Passwort',
      passwordConfirmation: 'Passwort bestätigen',
      submit: 'Konto erstellen',
      existingAccount: 'Du hast bereits ein Konto?',
      name: 'Name',
    },
    password: {
      forgotTitle: 'Passwort zurücksetzen',
      forgotDescription:
        'Gib deine E-Mail-Adresse ein. Wir senden dir einen Link zum Zurücksetzen des Passworts.',
      sendResetLink: 'Link zum Zurücksetzen senden',
      resetTitle: 'Neues Passwort wählen',
      resetSubmit: 'Passwort zurücksetzen',
      confirmTitle: 'Passwort bestätigen',
    },
    verification: {
      title: 'E-Mail bestätigen',
      resend: 'Bestätigungs-E-Mail erneut senden',
    },
    twoFactor: {
      title: 'Zwei-Faktor-Authentifizierung',
      code: 'Authentifizierungscode',
      recoveryCode: 'Wiederherstellungscode',
      submit: 'Weiter',
    },
    invitation: {
      title: 'Allianz-Einladung',
      accept: 'Einladung annehmen',
    },
  },
  authExperience: {
    shell: {
      headline: 'Für Allianzführung gemacht.',
      intro:
        'Sicherer Zugriff auf die Werkzeuge, mit denen deine Allianz koordiniert, rekrutiert und sich vorbereitet.',
    },
    login: {
      intro: 'Greife auf alle Allianzen zu, die mit deinem globalen Konto verknüpft sind.',
      invitationNotice:
        'Melde dich mit dem eingeladenen Konto an, um die Allianz-Einladung weiter anzunehmen.',
      needAccount: 'Du brauchst ein Konto?',
      register: 'Registrieren',
    },
    register: {
      intro: 'Eine globale Identität kann mehreren Allianzen angehören.',
      invitationNotice:
        'Du wurdest als {email} zu {alliance} eingeladen. Mit der Kontoerstellung wird die Einladung ebenfalls angenommen.',
      invitationOnly:
        'Die Registrierung ist derzeit nur per Einladung möglich. Öffne den Einladungslink deiner Allianz.',
      timezone: 'Zeitzone',
      passwordHint: 'Mindestens 12 Zeichen mit Groß- und Kleinbuchstaben sowie einer Zahl.',
      existingAccount: 'Du hast bereits ein Konto?',
    },
    invitation: {
      join: '{alliance} beitreten',
      forEmail: 'Diese Einladung ist für {email}.',
      expires: 'Läuft ab: {date}',
      wrongAccount:
        'Du bist als {email} angemeldet. Melde dich mit der eingeladenen E-Mail-Adresse an, um diese Einladung anzunehmen.',
      createAndJoin: 'Konto erstellen und beitreten',
      signInAccept: 'Anmelden und annehmen',
    },
    password: {
      backToSignIn: 'Zurück zur Anmeldung',
      resetIntro: 'Das Zurücksetzen deines Passworts widerruft persönliche Zugriffstoken.',
      newPassword: 'Neues Passwort',
      confirmNewPassword: 'Neues Passwort bestätigen',
      confirmDescription:
        'Diese Aktion ändert Allianz-Zugriff oder Berechtigungen. Bestätige deshalb dein Passwort erneut.',
      recoveryKicker: 'Kontowiederherstellung',
      recoveryNeutral:
        'Wenn für diese E-Mail eine gültige Kennwort-Anmeldung vorhanden ist, senden wir Ihnen eine sichere Anleitung zum Zurücksetzen des Kennworts.',
      checkInboxKicker: 'Überprüfen Sie Ihren Posteingang',
      checkInboxHeading: 'Wiederherstellungsanweisungen angefordert',
      checkInboxDescription:
        'Wenn für die eingegebene Adresse ein gültiger Kennwort-Zugangscode vorhanden ist, wurden Anweisungen zum Zurücksetzen des Kennworts gesendet. Aus Sicherheitsgründen können wir nicht bestätigen, ob ein Konto existiert oder welche Methoden es verwendet.',
      resetKicker: 'Passwort zurücksetzen',
      accountEmail: 'E-Mail-Adresse des Kontos',
      requirements: 'Ihr Passwort muss Folgendes enthalten:',
      requirementLength: 'Mindestens 12 Zeichen',
      requirementUpper: 'Ein Großbuchstabe',
      requirementLower: 'Ein Kleinbuchstabe',
      requirementNumber: 'Eine Zahl',
      showPassword: 'Passwort anzeigen »',
      hidePassword: 'Passwort ausblenden »',
      validationSummary: 'Überprüfen Sie die markierten Felder, bevor Sie fortfahren.',
    },
    verification: {
      description:
        'Wir haben einen Bestätigungslink an die unten angegebene E-Mail-Adresse gesendet. Bestätige die Adresse vor geschützten Kontoaktionen.',
      sent: 'Ein neuer Bestätigungslink wurde gesendet.',
      kicker: 'E-Mail Verifizierung',
      instructions:
        'Öffnen Sie die Nachricht und folgen Sie dem Link, um die Bestätigung Ihrer E-Mail-Adresse abzuschließen.',
      securityNote:
        'Kingshot Alliance wird Sie niemals per E-Mail zur Passwort-Bestätigung fragen.',
      resendIn: 'Sie können in {seconds}s erneut senden',
      useAnotherAccount: 'Abmelden und ein anderes Konto verwenden',
    },
    twoFactor: {
      kicker: 'Sicherheitsprüfung',
      description: 'Gib den aktuellen sechsstelligen Code aus deiner Authenticator-App ein.',
      verifyCode: 'Code prüfen',
      useRecoveryCode: 'Wiederherstellungscode verwenden',
    },
    social: {
      continueWithGoogle: 'Weiter mit Google',
      createWithGoogle: 'Kingshot Alliance Konto mit Google erstellen',
      or: 'oder',
    },
    passkeys: {
      signIn: 'Mit Passkey anmelden',
      authenticating: 'Passkey wird geprüft…',
    },
    confirm: {
      title: 'Passwort bestätigen',
      description:
        'Verwenden Sie eine der angehängten Anmeldemethoden, bevor Sie mit dieser sensiblen Kontoänderung fortfahren.',
      usePassword: 'Bestätigen mit Passwort',
      useGoogle: 'Bestätigen Sie mit Google',
      usePasskey: 'Mit Passkey bestätigen',
    },
  },
} satisfies MessageCatalogue;

export default messages;
