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
      forgotTitle: 'Dein Konto wiederherstellen',
      forgotDescription:
        'Gib die E-Mail-Adresse ein, die mit deinem Kingshot Alliance-Konto verknüpft ist.',
      sendResetLink: 'Anleitung zur Wiederherstellung senden',
      resetTitle: 'Neues Passwort wählen',
      resetSubmit: 'Passwort zurücksetzen',
      confirmTitle: 'Bestätige deine Identität',
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
        'Rekrutiere Gouverneure, bereite Events vor, verwalte deine Allianz und behalte das Königreich an einem Ort im Blick.',
    },
    login: {
      intro:
        'Melde dich mit deinem Konto an und wähle dann den Gouverneur aus, den du verwenden möchtest.',
      invitationNotice:
        'Melde dich mit dem eingeladenen Konto an, um die Allianz-Einladung weiter anzunehmen.',
      needAccount: 'Du brauchst ein Konto?',
      register: 'Registrieren',
    },
    register: {
      intro:
        'Ein Konto kann mehrere Gouverneure besitzen. Der Allianzrang und die Aufgaben im Königreich gehören immer zum ausgewählten Gouverneur.',
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
      resetIntro: 'Wähle ein neues Kingshot Alliance-Passwort für das unten angezeigte Konto.',
      newPassword: 'Neues Passwort',
      confirmNewPassword: 'Neues Passwort bestätigen',
      confirmDescription:
        'Dies ändert den geschützten Zugriff auf die Allianz. Bestätige deshalb deine Identität, bevor du fortfährst.',
      recoveryKicker: 'Kontowiederherstellung',
      recoveryNeutral:
        'Wenn für diese E-Mail-Adresse eine Passwortwiederherstellung möglich ist, senden wir eine sichere Anleitung zum Zurücksetzen des Passworts.',
      checkInboxKicker: 'Prüfe deinen Posteingang',
      checkInboxHeading: 'Anleitung zur Wiederherstellung angefordert',
      checkInboxDescription:
        'Wenn für die eingegebene Adresse eine Passwortwiederherstellung möglich ist, wurde eine Anleitung zum Zurücksetzen des Passworts gesendet. Aus Sicherheitsgründen können wir weder bestätigen, ob ein Konto existiert, noch welche Anmeldemethoden es verwendet.',
      resetKicker: 'Passwort sicher zurücksetzen',
      accountEmail: 'E-Mail-Adresse des Kontos',
      requirements: 'Ihr Passwort muss Folgendes enthalten:',
      requirementLength: 'Mindestens 12 Zeichen',
      requirementUpper: 'Ein Großbuchstabe',
      requirementLower: 'Ein Kleinbuchstabe',
      requirementNumber: 'Eine Ziffer',
      showPassword: 'Passwort anzeigen »',
      hidePassword: 'Passwort ausblenden »',
      validationSummary: 'Überprüfen Sie die markierten Felder, bevor Sie fortfahren.',
    },
    verification: {
      description:
        'Wir haben einen sicheren Bestätigungslink für dein Kingshot Alliance-Konto an diese Adresse gesendet:',
      sent: 'Ein neuer Bestätigungslink wurde gesendet.',
      kicker: 'E-Mail-Bestätigung',
      instructions:
        'Öffnen Sie die Nachricht und folgen Sie dem Link, um die Bestätigung Ihrer E-Mail-Adresse abzuschließen.',
      securityNote:
        'Kingshot Alliance wird dich in einer E-Mail zur Bestätigung deiner E-Mail-Adresse niemals nach deinem Passwort fragen.',
      resendIn: 'Du kannst in {seconds} Sekunden erneut senden',
      useAnotherAccount: 'Abmelden und ein anderes Konto verwenden',
    },
    twoFactor: {
      kicker: 'Kontosicherheit',
      description: 'Gib den aktuellen sechsstelligen Code aus deiner Authenticator-App ein.',
      verifyCode: 'Code prüfen',
      useRecoveryCode: 'Wiederherstellungscode verwenden',
    },
    social: {
      continueWithGoogle: 'Weiter mit Google',
      createWithGoogle: 'Kingshot Alliance-Konto mit Google erstellen',
      or: 'oder',
    },
    passkeys: {
      signIn: 'Mit Passkey anmelden',
      authenticating: 'Passkey wird geprüft…',
    },
    confirm: {
      title: 'Bestätige deine Identität',
      description:
        'Verwende eine der mit deinem Konto verknüpften Anmeldemethoden, bevor du diese sicherheitsrelevante Kontoänderung fortsetzt.',
      usePassword: 'Bestätigen mit Passwort',
      useGoogle: 'Bestätigen Sie mit Google',
      usePasskey: 'Mit Passkey bestätigen',
    },
  },
} satisfies MessageCatalogue;

export default messages;
