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
      email: 'Email',
      password: 'Password',
    },
    register: {
      title: 'Crea account',
      name: 'Nome',
      passwordConfirmation: 'Conferma password',
      submit: 'Crea account',
      existingAccount: 'Hai già un account?',
      email: 'Email',
      password: 'Password',
    },
    password: {
      forgotTitle: 'Recupera il tuo account',
      forgotDescription: 'Inserisci l’indirizzo email associato al tuo account Kingshot Alliance.',
      sendResetLink: 'Invia le istruzioni di recupero',
      resetTitle: 'Scegli una nuova password',
      resetSubmit: 'Reimposta password',
      confirmTitle: 'Conferma la tua identità',
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
        'Recluta Governatori, prepara Eventi, gestisci la tua Alleanza e segui il Regno in un unico posto.',
    },
    login: {
      intro: 'Accedi al tuo account, poi scegli il Governatore che vuoi usare.',
      invitationNotice:
        'Accedi con l’account invitato per continuare ad accettare l’invito dell’alleanza.',
      needAccount: 'Ti serve un account?',
      register: 'Registrati',
    },
    register: {
      intro:
        'Un account può possedere più Governatori. Il grado nell’Alleanza e gli incarichi nel Regno dipendono sempre dal Governatore scelto.',
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
      resetIntro: 'Scegli una nuova password di Kingshot Alliance per l’account qui sotto.',
      newPassword: 'Nuova password',
      confirmNewPassword: 'Conferma nuova password',
      confirmDescription:
        'Questa azione modifica l’accesso protetto all’Alleanza. Conferma la tua identità prima di continuare.',
      recoveryKicker: 'RECUPERO DELL’ACCOUNT',
      recoveryNeutral:
        'Se il recupero della password è disponibile per quell’indirizzo email, invieremo istruzioni sicure per reimpostarla.',
      checkInboxKicker: 'CONTROLLA LA POSTA IN ARRIVO',
      checkInboxHeading: 'Istruzioni di recupero richieste',
      checkInboxDescription:
        'Se il recupero della password è disponibile per l’indirizzo inserito, sono state inviate le istruzioni per reimpostarla. Per motivi di sicurezza, non possiamo confermare l’esistenza di un account né i metodi di accesso che utilizza.',
      resetKicker: 'REIMPOSTAZIONE SICURA DELLA PASSWORD',
      accountEmail: "Email dell'account",
      requirements: 'La password deve includere:',
      requirementLength: 'Almeno 12 caratteri',
      requirementUpper: 'Una lettera maiuscola',
      requirementLower: 'Una lettera minuscola',
      requirementNumber: 'Una cifra',
      showPassword: 'Mostra la password',
      hidePassword: 'Nascondi la password',
      validationSummary: 'Controllare i campi evidenziati prima di continuare.',
    },
    verification: {
      description:
        'Abbiamo inviato un link di verifica sicuro per il tuo account Kingshot Alliance a:',
      sent: 'È stato inviato un nuovo link di verifica.',
      kicker: 'VERIFICA DELL’INDIRIZZO EMAIL',
      instructions:
        'Apri il messaggio e segui il link per completare la verifica del tuo indirizzo email.',
      securityNote:
        'Kingshot Alliance non ti chiederà mai la password in un messaggio di verifica dell’indirizzo email.',
      resendIn: 'Potrai inviare di nuovo tra {seconds} secondi',
      useAnotherAccount: 'Esci e usa un altro account',
    },
    twoFactor: {
      kicker: 'Sicurezza dell’account',
      description: 'Inserisci il codice attuale di sei cifre dalla tua app di autenticazione.',
      verifyCode: 'Verifica codice',
      useRecoveryCode: 'Usa codice di recupero',
    },
    social: {
      continueWithGoogle: 'Continua con Google',
      createWithGoogle: 'Crea un account Kingshot Alliance con Google',
      or: 'o',
    },
    passkeys: {
      signIn: 'Accedi con una chiave di accesso',
      authenticating: 'Verifica della chiave di accesso…',
    },
    confirm: {
      title: 'Conferma la tua identità',
      description:
        'Usa uno dei metodi di accesso collegati al tuo account prima di continuare con questa modifica sensibile dell’account.',
      usePassword: 'Conferma con password',
      useGoogle: 'Conferma con Google',
      usePasskey: 'Conferma con una chiave di accesso',
    },
  },
} satisfies MessageCatalogue;

export default messages;
