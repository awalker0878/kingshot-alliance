import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Se connecter',
      email: 'E-mail',
      password: 'Mot de passe',
      remember: 'Se souvenir de moi',
      forgotPassword: 'Mot de passe oublié ?',
      submit: 'Se connecter',
      createAccount: 'Créer un compte',
      invitation: 'Vous avez une invitation ?',
    },
    register: {
      title: 'Créer un compte',
      name: 'Nom',
      email: 'E-mail',
      password: 'Mot de passe',
      passwordConfirmation: 'Confirmer le mot de passe',
      submit: 'Créer un compte',
      existingAccount: 'Vous avez déjà un compte ?',
    },
    password: {
      forgotTitle: 'Réinitialiser votre mot de passe',
      forgotDescription:
        'Saisissez votre adresse e-mail et nous vous enverrons un lien de réinitialisation.',
      sendResetLink: 'Envoyer le lien de réinitialisation',
      resetTitle: 'Choisissez un nouveau mot de passe',
      resetSubmit: 'Réinitialiser le mot de passe',
      confirmTitle: 'Confirmer votre mot de passe',
    },
    verification: {
      title: 'Vérifiez votre e-mail',
      resend: 'Renvoyer l’e-mail de vérification',
    },
    twoFactor: {
      title: 'Authentification à deux facteurs',
      code: 'Code d’authentification',
      recoveryCode: 'Code de récupération',
      submit: 'Continuer',
    },
    invitation: {
      title: 'Invitation d’alliance',
      accept: 'Accepter l’invitation',
    },
  },
  authExperience: {
    shell: {
      headline: 'Pensé pour les chefs d’alliance.',
      intro:
        'Un accès sécurisé aux outils utilisés par votre alliance pour coordonner, recruter et préparer la suite.',
    },
    login: {
      intro: 'Accédez à toutes les alliances liées à votre compte global.',
      invitationNotice:
        'Connectez-vous avec le compte invité pour poursuivre l’acceptation de l’invitation.',
      needAccount: 'Besoin d’un compte ?',
      register: 'Créer un compte',
    },
    register: {
      intro: 'Une identité globale peut appartenir à plusieurs alliances.',
      invitationNotice:
        'Vous avez été invité à rejoindre {alliance} avec {email}. La création du compte acceptera aussi cette invitation.',
      invitationOnly:
        'L’inscription est actuellement sur invitation uniquement. Ouvrez le lien envoyé par votre alliance.',
      timezone: 'Fuseau horaire',
      passwordHint: 'Au moins 12 caractères avec majuscules, minuscules et un chiffre.',
      existingAccount: 'Vous avez déjà un compte ?',
    },
    invitation: {
      join: 'Rejoindre {alliance}',
      forEmail: 'Cette invitation est destinée à {email}.',
      expires: 'Expire le {date}',
      wrongAccount:
        'Vous êtes connecté avec {email}. Connectez-vous avec l’adresse invitée pour accepter cette invitation.',
      createAndJoin: 'Créer un compte et rejoindre',
      signInAccept: 'Se connecter pour accepter',
    },
    password: {
      backToSignIn: 'Retour à la connexion',
      resetIntro: 'La réinitialisation du mot de passe révoque les jetons d’accès personnels.',
      newPassword: 'Nouveau mot de passe',
      confirmNewPassword: 'Confirmer le nouveau mot de passe',
      confirmDescription:
        'Cette action modifie l’accès ou les autorisations de l’alliance. Votre mot de passe doit donc être confirmé.',
    },
    verification: {
      description:
        'Nous avons envoyé un lien de vérification à {email}. Vérifiez l’adresse avant les actions de compte protégées.',
      sent: 'Un nouveau lien de vérification a été envoyé.',
    },
    twoFactor: {
      kicker: 'Contrôle de sécurité',
      description:
        'Saisissez le code actuel à six chiffres de votre application d’authentification.',
      verifyCode: 'Vérifier le code',
      useRecoveryCode: 'Utiliser un code de récupération',
    },
  },
} satisfies MessageCatalogue;

export default messages;
