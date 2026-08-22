import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Rappels des avantages de position',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Gestion du compte',
      title: 'Compte et sécurité',
      intro:
        'Gérez votre identité, la vérification, le mot de passe, l’authentification à deux facteurs et les sessions actives.',
      passwordUpdated:
        'Votre mot de passe a été mis à jour et les autres sessions authentifiées ont été révoquées.',
      sessionsRevoked: 'Les autres sessions authentifiées ont été déconnectées.',
      twoFactorDisabled: 'L’authentification à deux facteurs a été désactivée.',
      profileTitle: 'Profil',
      profileIntro: 'Modifier votre adresse e-mail nécessite une nouvelle vérification.',
      timezone: 'Fuseau horaire',
      saveProfile: 'Enregistrer le profil',
      emailVerification: 'Vérification de l’e-mail',
      verified: 'Vérifié',
      pending: 'En attente',
      twoFactorState: 'Authentification à deux facteurs',
      enabled: 'Activée',
      setupPending: 'Configuration en attente',
      notEnabled: 'Non activée',
      twoFactorTitle: 'Authentification à deux facteurs',
      twoFactorIntro:
        'Protégez la connexion avec une application d’authentification. Les codes de récupération ne sont affichés qu’à leur création ou régénération.',
      startSetup: 'Commencer la configuration',
      authenticatorSecret: 'Secret de l’authentificateur',
      provisioningUri: 'URI de configuration',
      authenticationCode: 'Code d’authentification',
      confirm: 'Confirmer',
      saveRecoveryCodes: 'Enregistrez ces codes de récupération maintenant',
      recoveryIntro:
        'Chaque code ne fonctionne qu’une fois. Conservez-les séparément de ce compte.',
      regenerateRecoveryCodes: 'Régénérer les codes de récupération',
      disableTwoFactor: 'Désactiver l’authentification à deux facteurs',
      passwordTitle: 'Changer le mot de passe',
      passwordIntro:
        'Changer le mot de passe déconnecte les autres appareils et ferme les autres accès actifs.',
      currentPassword: 'Mot de passe actuel',
      newPassword: 'Nouveau mot de passe',
      confirmNewPassword: 'Confirmer le nouveau mot de passe',
      updatePassword: 'Mettre à jour le mot de passe',
      sessionsTitle: 'Autres sessions',
      sessionsIntro: 'Déconnectez toutes les sessions authentifiées sauf cet appareil.',
      signOutOthers: 'Déconnecter les autres appareils',
      dangerTitle: 'Zone sensible',
      deleteAccount: 'Suppression du compte',
    },
    deletion: {
      eyebrow: 'Cycle de vie du compte',
      title: 'Suppression du compte',
      intro:
        'La suppression comprend un délai de réflexion de sept jours. La propriété active d’une alliance, l’accès administrateur de plateforme ou une conservation légale peuvent bloquer le traitement. Les comptes traités sont anonymisés plutôt que d’effacer l’historique d’audit.',
      currentRequest: 'Demande actuelle',
      status: 'Statut',
      eligibleAt: 'Éligible le',
      requestedAt: 'Demandé',
      processedAt: 'Traité',
      notYet: 'Pas encore',
      requestTitle: 'Demander la suppression',
      requestIntro:
        'Transférez d’abord la propriété de toute alliance. Les enregistrements soumis à une conservation légale ou requis pour la sécurité et l’audit restent pseudonymisés.',
      requestButton: 'Demander la suppression du compte',
      confirm:
        'Demander la suppression du compte ? Un délai de sept jours et des contrôles de propriété/conservation légale s’appliquent.',
      requested: 'Votre demande de suppression de compte a été enregistrée.',
      backToAccount: 'Retour au compte et sécurité',
    },
  },
} satisfies MessageCatalogue;

export default messages;
