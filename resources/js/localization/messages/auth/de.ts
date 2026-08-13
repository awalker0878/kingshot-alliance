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
    },
    verification: {
      description:
        'Wir haben einen Bestätigungslink an {email} gesendet. Bestätige die Adresse vor geschützten Kontoaktionen.',
      sent: 'Ein neuer Bestätigungslink wurde gesendet.',
    },
    twoFactor: {
      kicker: 'Sicherheitsprüfung',
      description: 'Gib den aktuellen sechsstelligen Code aus deiner Authenticator-App ein.',
      verifyCode: 'Code prüfen',
      useRecoveryCode: 'Wiederherstellungscode verwenden',
    },
  },
} satisfies MessageCatalogue;

export default messages;
