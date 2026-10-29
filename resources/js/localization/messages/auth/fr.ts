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
      forgotTitle: 'Récupérer votre compte',
      forgotDescription: 'Saisissez l’adresse e-mail associée à votre compte Kingshot Alliance.',
      sendResetLink: 'Envoyer les instructions de récupération',
      resetTitle: 'Choisissez un nouveau mot de passe',
      resetSubmit: 'Réinitialiser le mot de passe',
      confirmTitle: 'Confirmez votre identité',
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
        'Recrutez des Gouverneurs, préparez des Événements, gérez votre Alliance et suivez l’activité du Royaume au même endroit.',
    },
    login: {
      intro:
        'Connectez-vous à votre compte, puis choisissez le Gouverneur que vous souhaitez utiliser.',
      invitationNotice:
        'Connectez-vous avec le compte invité pour poursuivre l’acceptation de l’invitation.',
      needAccount: 'Besoin d’un compte ?',
      register: 'Créer un compte',
    },
    register: {
      intro:
        'Un compte peut posséder plusieurs Gouverneurs. Le rang dans l’Alliance et les fonctions dans le Royaume sont toujours ceux du Gouverneur choisi.',
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
      resetIntro: 'Choisissez un nouveau mot de passe Kingshot Alliance pour le compte ci-dessous.',
      newPassword: 'Nouveau mot de passe',
      confirmNewPassword: 'Confirmer le nouveau mot de passe',
      confirmDescription:
        'Cette action modifie l’accès protégé à l’Alliance. Confirmez votre identité avant de continuer.',
      recoveryKicker: 'RÉCUPÉRATION DU COMPTE',
      recoveryNeutral:
        'Si la récupération du mot de passe est disponible pour cette adresse e-mail, nous enverrons des instructions sécurisées pour le réinitialiser.',
      checkInboxKicker: 'VÉRIFIEZ VOTRE BOÎTE DE RÉCEPTION',
      checkInboxHeading: 'Instructions de récupération demandées',
      checkInboxDescription:
        'Si la récupération du mot de passe est disponible pour l’adresse saisie, des instructions de réinitialisation ont été envoyées. Pour des raisons de sécurité, nous ne pouvons confirmer ni l’existence d’un compte ni les méthodes de connexion qu’il utilise.',
      resetKicker: 'RÉINITIALISATION SÉCURISÉE DU MOT DE PASSE',
      accountEmail: 'Email du compte',
      requirements: 'Votre mot de passe doit inclure:',
      requirementLength: 'Au moins 12 caractères',
      requirementUpper: 'Une lettre majuscule',
      requirementLower: 'Une lettre minuscule',
      requirementNumber: 'Un chiffre',
      showPassword: 'Afficher le mot de passe',
      hidePassword: 'Masquer le mot de passe',
      validationSummary: 'Vérifiez les champs surlignés avant de continuer.',
    },
    verification: {
      description:
        'Nous avons envoyé un lien de vérification sécurisé pour votre compte Kingshot Alliance à :',
      sent: 'Un nouveau lien de vérification a été envoyé.',
      kicker: 'VÉRIFICATION DE L’ADRESSE E-MAIL',
      instructions:
        'Ouvrez le message et suivez le lien pour terminer la vérification de votre adresse e-mail.',
      securityNote:
        'Kingshot Alliance ne vous demandera jamais votre mot de passe dans un message de vérification de votre adresse e-mail.',
      resendIn: 'Vous pourrez renvoyer le message dans {seconds} secondes',
      useAnotherAccount: 'Se déconnecter et utiliser un autre compte',
    },
    twoFactor: {
      kicker: 'Sécurité du compte',
      description:
        'Saisissez le code actuel à six chiffres de votre application d’authentification.',
      verifyCode: 'Vérifier le code',
      useRecoveryCode: 'Utiliser un code de récupération',
    },
    social: {
      continueWithGoogle: 'Continuer avec Google',
      createWithGoogle: 'Créer un compte Kingshot Alliance avec Google',
      or: 'ou',
    },
    passkeys: {
      signIn: 'Se connecter avec une clé d’accès',
      authenticating: 'Vérification de la clé d’accès…',
    },
    confirm: {
      title: 'Confirmez votre identité',
      description:
        'Utilisez l’une des méthodes de connexion associées à votre compte avant de poursuivre cette modification sensible du compte.',
      usePassword: 'Confirmer avec mot de passe',
      useGoogle: 'Confirmez avec Google',
      usePasskey: 'Confirmer avec une clé d’accès',
    },
  },
} satisfies MessageCatalogue;

export default messages;
