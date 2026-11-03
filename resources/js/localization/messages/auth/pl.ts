import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Zaloguj się',
      email: 'E-mail',
      password: 'Hasło',
      remember: 'Zapamiętaj mnie',
      forgotPassword: 'Nie pamiętasz hasła?',
      submit: 'Zaloguj się',
      createAccount: 'Utwórz konto',
      invitation: 'Masz zaproszenie?',
    },
    register: {
      title: 'Utwórz konto',
      name: 'Nazwa',
      email: 'E-mail',
      password: 'Hasło',
      passwordConfirmation: 'Potwierdź hasło',
      submit: 'Utwórz konto',
      existingAccount: 'Masz już konto?',
    },
    password: {
      forgotTitle: 'Odzyskaj swoje konto',
      forgotDescription: 'Wpisz adres e-mail powiązany z Twoim kontem Kingshot Alliance.',
      sendResetLink: 'Wyślij instrukcje odzyskiwania',
      resetTitle: 'Wybierz nowe hasło',
      resetSubmit: 'Zresetuj hasło',
      confirmTitle: 'Potwierdź swoją tożsamość',
    },
    verification: {
      title: 'Zweryfikuj e-mail',
      resend: 'Wyślij ponownie e-mail weryfikacyjny',
    },
    twoFactor: {
      title: 'Uwierzytelnianie dwuskładnikowe',
      code: 'Kod uwierzytelniający',
      recoveryCode: 'Kod odzyskiwania',
      submit: 'Dalej',
    },
    invitation: {
      title: 'Zaproszenie do sojuszu',
      accept: 'Przyjmij zaproszenie',
    },
  },
  authExperience: {
    shell: {
      headline: 'Stworzone dla liderów sojuszu.',
      intro:
        'Rekrutuj Gubernatorów, przygotowuj Wydarzenia, zarządzaj Sojuszem i śledź sytuację w Królestwie w jednym miejscu.',
    },
    login: {
      intro: 'Zaloguj się na swoje konto, a następnie wybierz Gubernatora, którego chcesz używać.',
      invitationNotice:
        'Zaloguj się na zaproszone konto, aby kontynuować przyjmowanie zaproszenia do sojuszu.',
      needAccount: 'Potrzebujesz konta?',
      register: 'Zarejestruj się',
    },
    register: {
      intro:
        'Jedno konto może mieć kilku Gubernatorów. Ranga w Sojuszu i obowiązki w Królestwie zawsze dotyczą wybranego Gubernatora.',
      invitationNotice:
        'Zaproszono Cię do {alliance} jako {email}. Utworzenie konta zaakceptuje również to zaproszenie.',
      invitationOnly:
        'Rejestracja jest obecnie możliwa tylko z zaproszenia. Otwórz link wysłany przez sojusz.',
      timezone: 'Strefa czasowa',
      passwordHint: 'Co najmniej 12 znaków, w tym wielkie i małe litery oraz cyfra.',
      existingAccount: 'Masz już konto?',
    },
    invitation: {
      join: 'Dołącz do {alliance}',
      forEmail: 'To zaproszenie jest dla {email}.',
      expires: 'Wygasa: {date}',
      wrongAccount:
        'Jesteś zalogowany jako {email}. Zaloguj się adresem, na który wysłano zaproszenie.',
      createAndJoin: 'Utwórz konto i dołącz',
      signInAccept: 'Zaloguj się, aby zaakceptować',
    },
    password: {
      backToSignIn: 'Wróć do logowania',
      resetIntro: 'Wybierz nowe hasło Kingshot Alliance dla poniższego konta.',
      newPassword: 'Nowe hasło',
      confirmNewPassword: 'Potwierdź nowe hasło',
      confirmDescription:
        'Ta czynność zmienia chroniony dostęp do Sojuszu. Potwierdź swoją tożsamość, zanim przejdziesz dalej.',
      recoveryKicker: 'ODZYSKIWANIE KONTA',
      recoveryNeutral:
        'Jeśli dla tego adresu e-mail dostępne jest odzyskiwanie hasła, wyślemy bezpieczne instrukcje jego resetowania.',
      checkInboxKicker: 'SPRAWDŹ SKRZYNKĘ ODBIORCZĄ',
      checkInboxHeading: 'Poproszono o instrukcje odzyskiwania',
      checkInboxDescription:
        'Jeśli dla podanego adresu dostępne jest odzyskiwanie hasła, wysłano instrukcje jego resetowania. Ze względów bezpieczeństwa nie możemy potwierdzić istnienia konta ani używanych przez nie metod logowania.',
      resetKicker: 'BEZPIECZNE RESETOWANIE HASŁA',
      accountEmail: 'Adres e-mail konta',
      requirements: 'Twoje hasło musi zawierać:',
      requirementLength: 'Co najmniej 12 znaków',
      requirementUpper: 'Wielka litera',
      requirementLower: 'Mała litera',
      requirementNumber: 'Cyfra',
      showPassword: 'Pokaż hasło',
      hidePassword: 'Ukryj hasło',
      validationSummary: 'Przed kontynuowaniem sprawdź podświetlone pola.',
    },
    verification: {
      description:
        'Wysłaliśmy bezpieczny link weryfikacyjny dla Twojego konta Kingshot Alliance na adres:',
      sent: 'Wysłano nowy link weryfikacyjny.',
      kicker: 'WERYFIKACJA ADRESU E-MAIL',
      instructions: 'Otwórz wiadomość i kliknij link, aby zakończyć weryfikację adresu e-mail.',
      securityNote:
        'Kingshot Alliance nigdy nie poprosi o Twoje hasło w wiadomości weryfikującej adres e-mail.',
      resendIn: 'Możesz wysłać ponownie za {seconds} sekund',
      useAnotherAccount: 'Wyloguj się i użyj innego konta',
    },
    twoFactor: {
      kicker: 'Bezpieczeństwo konta',
      description: 'Wpisz aktualny sześciocyfrowy kod z aplikacji uwierzytelniającej.',
      verifyCode: 'Zweryfikuj kod',
      useRecoveryCode: 'Użyj kodu odzyskiwania',
    },
    social: {
      continueWithGoogle: 'Kontynuuj z Google',
      createWithGoogle: 'Utwórz konto Kingshot Alliance przez Google',
      or: 'lub',
    },
    passkeys: {
      signIn: 'Zaloguj się kluczem dostępu',
      authenticating: 'Sprawdzanie klucza dostępu…',
    },
    confirm: {
      title: 'Potwierdź swoją tożsamość',
      description:
        'Przed kontynuowaniem tej istotnej dla bezpieczeństwa zmiany konta użyj jednej z powiązanych z nim metod logowania.',
      usePassword: 'Potwierdź hasłem',
      useGoogle: 'Potwierdź z Google',
      usePasskey: 'Potwierdź kluczem dostępu',
    },
  },
} satisfies MessageCatalogue;

export default messages;
