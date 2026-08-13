import type { MessageCatalogue } from '../../types';

const messages = {
  accountExperience: {
    account: {
      eyebrow: 'Gestione account',
      title: 'Account e sicurezza',
      intro:
        'Gestisci identità, verifica, password, autenticazione a due fattori e sessioni attive.',
      passwordUpdated:
        'La password è stata aggiornata e le altre sessioni autenticate sono state revocate.',
      sessionsRevoked: 'Le altre sessioni autenticate sono state disconnesse.',
      twoFactorDisabled: 'L’autenticazione a due fattori è stata disattivata.',
      profileTitle: 'Profilo',
      profileIntro: 'La modifica dell’e-mail richiede una nuova verifica.',
      timezone: 'Fuso orario',
      saveProfile: 'Salva profilo',
      emailVerification: 'Verifica e-mail',
      verified: 'Verificata',
      pending: 'In attesa',
      twoFactorState: 'Autenticazione a due fattori',
      enabled: 'Attiva',
      setupPending: 'Configurazione in sospeso',
      notEnabled: 'Non attiva',
      twoFactorTitle: 'Autenticazione a due fattori',
      twoFactorIntro:
        'Proteggi l’accesso con un autenticatore TOTP. I codici di recupero vengono mostrati solo quando creati o rigenerati.',
      startSetup: 'Avvia configurazione',
      authenticatorSecret: 'Segreto autenticatore',
      provisioningUri: 'URI di provisioning',
      authenticationCode: 'Codice di autenticazione',
      confirm: 'Conferma',
      saveRecoveryCodes: 'Salva ora questi codici di recupero',
      recoveryIntro:
        'Ogni codice funziona una sola volta. Conservali separatamente da questo account.',
      regenerateRecoveryCodes: 'Rigenera codici di recupero',
      disableTwoFactor: 'Disattiva autenticazione a due fattori',
      passwordTitle: 'Cambia password',
      passwordIntro:
        'La modifica della password revoca i token di accesso personali e invalida le altre sessioni autenticate.',
      currentPassword: 'Password attuale',
      newPassword: 'Nuova password',
      confirmNewPassword: 'Conferma nuova password',
      updatePassword: 'Aggiorna password',
      sessionsTitle: 'Altre sessioni',
      sessionsIntro: 'Revoca tutte le sessioni autenticate tranne questo dispositivo.',
      signOutOthers: 'Disconnetti gli altri dispositivi',
      dangerTitle: 'Zona pericolosa',
      deleteAccount: 'Eliminazione account',
    },
    deletion: {
      eyebrow: 'Ciclo di vita account',
      title: 'Eliminazione account',
      intro:
        'L’eliminazione prevede sette giorni di attesa. La proprietà attiva di un’alleanza, l’accesso amministratore della piattaforma e i blocchi legali possono impedirne l’elaborazione. Gli account elaborati vengono anonimizzati senza rimuovere la cronologia di audit.',
      currentRequest: 'Richiesta corrente',
      status: 'Stato',
      eligibleAt: 'Idoneo dal',
      requestedAt: 'Richiesto',
      processedAt: 'Elaborato',
      notYet: 'Non ancora',
      requestTitle: 'Richiedi eliminazione',
      requestIntro:
        'Trasferisci prima la proprietà di ogni alleanza. I record soggetti a blocco legale o necessari per sicurezza e audit restano pseudonimizzati.',
      requestButton: 'Richiedi eliminazione account',
      confirm:
        'Richiedere l’eliminazione dell’account? Si applicano sette giorni di attesa e controlli su proprietà e blocchi legali.',
      requested: 'La richiesta di eliminazione dell’account è stata registrata.',
      backToAccount: 'Torna ad account e sicurezza',
    },
  },
} satisfies MessageCatalogue;

export default messages;
