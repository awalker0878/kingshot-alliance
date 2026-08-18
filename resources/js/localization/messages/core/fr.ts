import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Langue',
    signIn: 'Se connecter',
    signOut: 'Se déconnecter',
    createAccount: 'Créer un compte',
    continue: 'Continuer',
    cancel: 'Annuler',
    save: 'Enregistrer',
    close: 'Fermer',
    loading: 'Chargement',
    openNavigation: 'Ouvrir la navigation',
    closeNavigation: 'Fermer la navigation',
    playerAlliance: 'Alliance du joueur actif',
    noPlayerAlliance: 'Le joueur actif n’a aucune adhésion active à une alliance.',
    skipToContent: 'Aller au contenu',
  },
  navigation: {
    home: 'Accueil',
    dashboard: 'Tableau de bord',
    events: 'Événements',
    roster: 'Membres',
    recruitment: 'Recrutement',
    content: 'Contenu',
    kingdom: 'Royaume',
    transfers: 'Transferts',
    integrations: 'Intégrations',
    profile: 'Profil',
    settings: 'Paramètres',
    allianceOperations: 'Opérations d’alliance',
    kingdomOperations: 'Opérations du royaume',
    account: 'Compte',
  },
  application: {
    dashboard: {
      title: 'Tableau de bord',
      eyebrow: 'Commandement de l’alliance',
      welcome: 'Bienvenue, {name}',
      verificationPending: 'Vérification de l’e-mail en attente',
      playerContextTitle: 'Joueur actif',
      playerContextIntro:
        'Changer de joueur change l’identité de jeu utilisée pour les autorisations d’alliance et de royaume.',
      playerKingdom: 'Royaume #{kingdom}',
      playerAuthorityIntro:
        'L’adhésion, le rang, les rôles, les autorisations de royaume et les actions de jeu sont déterminés uniquement par ce joueur.',
      selectPlayer: 'Sélectionner le gouverneur',
      playerAllianceTitle: 'Alliance du joueur actif',
      playerAllianceIntro:
        'Les outils d’alliance utilisent uniquement l’adhésion, le rang et les rôles du joueur actif.',
      noPlayerAllianceTitle: 'Ce joueur n’appartient à aucune alliance',
      noPlayerAllianceIntro:
        'Changez de joueur ou créez/rejoignez une alliance avec le joueur actif avant d’ouvrir les outils d’alliance.',
      openPlayerAlliance: 'Ouvrir l’alliance du joueur',
      roles: 'Rôles',
      kingdomAlliances: 'Alliances du royaume',
      transfers: 'Transferts',
      kingdomSettings: 'Paramètres du royaume',
      createTitle: 'Créer une alliance',
      createIntro:
        'Créez une alliance pour le joueur actif. Le royaume de l’alliance est dérivé de ce joueur, qui devient le premier R5.',
      allianceName: 'Nom de l’alliance',
      timezone: 'Fuseau horaire',
      create: 'Créer l’alliance',
    },
  },
} satisfies MessageCatalogue;

export default messages;
