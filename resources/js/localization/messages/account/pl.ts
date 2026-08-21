import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Przypomnienia o korzyściach stanowiska',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Zarządzanie kontem',
      title: 'Konto i bezpieczeństwo',
      intro:
        'Zarządzaj tożsamością, weryfikacją, hasłem, uwierzytelnianiem dwuskładnikowym i aktywnymi sesjami.',
      passwordUpdated:
        'Hasło zostało zaktualizowane, a pozostałe uwierzytelnione sesje wylogowane.',
      sessionsRevoked: 'Pozostałe uwierzytelnione sesje zostały wylogowane.',
      twoFactorDisabled: 'Uwierzytelnianie dwuskładnikowe zostało wyłączone.',
      profileTitle: 'Profil',
      profileIntro: 'Zmiana adresu e-mail wymaga ponownej weryfikacji.',
      timezone: 'Strefa czasowa',
      saveProfile: 'Zapisz profil',
      emailVerification: 'Weryfikacja e-mail',
      verified: 'Zweryfikowano',
      pending: 'Oczekuje',
      twoFactorState: 'Uwierzytelnianie dwuskładnikowe',
      enabled: 'Włączone',
      setupPending: 'Konfiguracja oczekuje',
      notEnabled: 'Wyłączone',
      twoFactorTitle: 'Uwierzytelnianie dwuskładnikowe',
      twoFactorIntro:
        'Chroń logowanie za pomocą aplikacji uwierzytelniającej. Kody odzyskiwania są wyświetlane tylko po utworzeniu lub ponownym wygenerowaniu.',
      startSetup: 'Rozpocznij konfigurację',
      authenticatorSecret: 'Sekret aplikacji uwierzytelniającej',
      provisioningUri: 'URI konfiguracji',
      authenticationCode: 'Kod uwierzytelniający',
      confirm: 'Potwierdź',
      saveRecoveryCodes: 'Zapisz teraz kody odzyskiwania',
      recoveryIntro: 'Każdy kod działa tylko raz. Przechowuj je oddzielnie od tego konta.',
      regenerateRecoveryCodes: 'Wygeneruj ponownie kody odzyskiwania',
      disableTwoFactor: 'Wyłącz uwierzytelnianie dwuskładnikowe',
      passwordTitle: 'Zmień hasło',
      passwordIntro:
        'Zmiana hasła wylogowuje inne urządzenia i zamyka pozostały aktywny dostęp.',
      currentPassword: 'Aktualne hasło',
      newPassword: 'Nowe hasło',
      confirmNewPassword: 'Potwierdź nowe hasło',
      updatePassword: 'Zaktualizuj hasło',
      sessionsTitle: 'Inne sesje',
      sessionsIntro: 'Wyloguj wszystkie uwierzytelnione sesje poza tym urządzeniem.',
      signOutOthers: 'Wyloguj inne urządzenia',
      dangerTitle: 'Strefa ryzyka',
      deleteAccount: 'Usuwanie konta',
    },
    deletion: {
      eyebrow: 'Cykl życia konta',
      title: 'Usuwanie konta',
      intro:
        'Usunięcie obejmuje siedmiodniowy okres oczekiwania. Aktywna własność sojuszu, dostęp administratora platformy i blokady prawne mogą zatrzymać proces. Przetworzone konta są anonimizowane bez usuwania historii audytu.',
      currentRequest: 'Bieżący wniosek',
      eligibleAt: 'Możliwe od',
      requestedAt: 'Zgłoszono',
      processedAt: 'Przetworzono',
      notYet: 'Jeszcze nie',
      requestTitle: 'Poproś o usunięcie',
      requestIntro:
        'Najpierw przenieś własność wszystkich sojuszy. Zapisy objęte blokadą prawną lub potrzebne do bezpieczeństwa i audytu pozostają w formie pseudonimizowanej.',
      requestButton: 'Poproś o usunięcie konta',
      confirm:
        'Poprosić o usunięcie konta? Obowiązuje siedmiodniowy okres oczekiwania oraz kontrole własności i blokad prawnych.',
      requested: 'Wniosek o usunięcie konta został zapisany.',
      backToAccount: 'Wróć do konta i bezpieczeństwa',
    },
  },
} satisfies MessageCatalogue;

export default messages;
