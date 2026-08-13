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
      forgotTitle: 'Zresetuj hasło',
      forgotDescription: 'Podaj adres e-mail, a wyślemy link do zresetowania hasła.',
      sendResetLink: 'Wyślij link resetujący',
      resetTitle: 'Wybierz nowe hasło',
      resetSubmit: 'Zresetuj hasło',
      confirmTitle: 'Potwierdź hasło',
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
        'Bezpieczny dostęp do narzędzi, których sojusz używa do koordynacji, rekrutacji i przygotowań.',
    },
    login: {
      intro: 'Uzyskaj dostęp do wszystkich sojuszy połączonych z globalnym kontem.',
      invitationNotice:
        'Zaloguj się na zaproszone konto, aby kontynuować przyjmowanie zaproszenia do sojuszu.',
      needAccount: 'Potrzebujesz konta?',
      register: 'Zarejestruj się',
    },
    register: {
      intro: 'Jedna globalna tożsamość może należeć do wielu sojuszy.',
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
      resetIntro: 'Zresetowanie hasła unieważnia osobiste tokeny dostępu.',
      newPassword: 'Nowe hasło',
      confirmNewPassword: 'Potwierdź nowe hasło',
      confirmDescription:
        'Ta czynność zmienia dostęp lub uprawnienia w sojuszu, dlatego trzeba ponownie potwierdzić hasło.',
    },
    verification: {
      description:
        'Wysłaliśmy link weryfikacyjny na {email}. Zweryfikuj adres przed chronionymi czynnościami na koncie.',
      sent: 'Wysłano nowy link weryfikacyjny.',
    },
    twoFactor: {
      kicker: 'Kontrola bezpieczeństwa',
      description: 'Wpisz aktualny sześciocyfrowy kod z aplikacji uwierzytelniającej.',
      verifyCode: 'Zweryfikuj kod',
      useRecoveryCode: 'Użyj kodu odzyskiwania',
    },
  },
} satisfies MessageCatalogue;

export default messages;
