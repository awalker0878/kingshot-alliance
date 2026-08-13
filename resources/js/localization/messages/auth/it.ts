import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Accedi',
      remember: 'Ricordami',
      forgotPassword: 'Password dimenticata?',
      submit: 'Accedi',
      createAccount: 'Crea account',
      invitation: 'Hai un invito?',
    },
    register: {
      title: 'Crea account',
      name: 'Nome',
      passwordConfirmation: 'Conferma password',
      submit: 'Crea account',
      existingAccount: 'Hai già un account?',
    },
    password: {
      forgotTitle: 'Reimposta la password',
      forgotDescription:
        'Inserisci il tuo indirizzo email e ti invieremo un link per reimpostare la password.',
      sendResetLink: 'Invia link di reimpostazione',
      resetTitle: 'Scegli una nuova password',
      resetSubmit: 'Reimposta password',
      confirmTitle: 'Conferma la password',
    },
    verification: {
      title: 'Verifica la tua email',
      resend: "Invia di nuovo l'email di verifica",
    },
    twoFactor: {
      title: 'Autenticazione a due fattori',
      code: 'Codice di autenticazione',
      recoveryCode: 'Codice di recupero',
      submit: 'Continua',
    },
    invitation: {
      title: "Invito all'alleanza",
      accept: 'Accetta invito',
    },
  },
  authExperience: {
    shell: {
      headline: 'Pensato per i leader di alleanza.',
      intro:
        'Accesso sicuro agli strumenti che la tua alleanza usa per coordinarsi, reclutare e prepararsi al prossimo passo.',
    },
    login: {
      intro: 'Accedi a tutte le alleanze collegate al tuo account globale.',
      invitationNotice:
        'Accedi con l’account invitato per continuare ad accettare l’invito dell’alleanza.',
      needAccount: 'Ti serve un account?',
      register: 'Registrati',
    },
    register: {
      intro: 'Una sola identità globale può appartenere a più alleanze.',
      invitationNotice:
        'Sei stato invitato in {alliance} come {email}. La creazione dell’account accetterà anche questo invito.',
      invitationOnly:
        'La registrazione è attualmente solo su invito. Apri il link inviato dalla tua alleanza.',
      timezone: 'Fuso orario',
      passwordHint: 'Almeno 12 caratteri con maiuscole, minuscole e un numero.',
      existingAccount: 'Hai già un account?',
    },
    invitation: {
      join: 'Unisciti a {alliance}',
      forEmail: 'Questo invito è per {email}.',
      expires: 'Scade il {date}',
      wrongAccount:
        'Hai effettuato l’accesso come {email}. Accedi con l’indirizzo invitato per accettare questo invito.',
      createAndJoin: 'Crea account e unisciti',
      signInAccept: 'Accedi per accettare',
    },
    password: {
      backToSignIn: 'Torna all’accesso',
      resetIntro: 'La reimpostazione della password revoca i token di accesso personali.',
      newPassword: 'Nuova password',
      confirmNewPassword: 'Conferma nuova password',
      confirmDescription:
        'Questa azione modifica accesso o autorizzazioni dell’alleanza, quindi devi confermare di nuovo la password.',
    },
    verification: {
      description:
        'Abbiamo inviato un link di verifica a {email}. Verifica l’indirizzo prima delle azioni protette sull’account.',
      sent: 'È stato inviato un nuovo link di verifica.',
    },
    twoFactor: {
      kicker: 'Controllo di sicurezza',
      description: 'Inserisci il codice attuale di sei cifre dalla tua app di autenticazione.',
      verifyCode: 'Verifica codice',
      useRecoveryCode: 'Usa codice di recupero',
    },
  },
} satisfies MessageCatalogue;

export default messages;
