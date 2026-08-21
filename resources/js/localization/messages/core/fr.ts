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
    playerAlliance: 'Alliance du Gouverneur actif',
    noPlayerAlliance: 'Le Gouverneur actif n’est actuellement dans aucune Alliance.',
    skipToContent: 'Aller au contenu',
  },
  navigation: {
    home: 'Accueil',
    dashboard: 'Vue d’ensemble de l’Alliance',
    events: 'Événements',
    roster: 'Membres de l’Alliance',
    recruitment: 'Recrutement',
    content: 'Panneau d’annonces',
    kingdom: 'Alliances du Royaume',
    transfers: 'Transfert de Royaume',
    integrations: 'Connexions',
    profile: 'Compte du Gouverneur',
    settings: 'Paramètres',
    allianceOperations: 'Alliance',
    kingdomOperations: 'Royaume',
    account: 'Compte du Gouverneur',
  },
  application: {
    dashboard: {
      title: 'Vue d’ensemble de l’Alliance',
      eyebrow: 'Votre Alliance',
      welcome: 'Bienvenue, Gouverneur {name}',
      verificationPending: 'Vérification de l’e-mail en attente',
      playerContextTitle: 'Gouverneur actif',
      playerContextIntro:
        'Changez de Gouverneur pour changer l’identité Kingshot utilisée pour les actions d’Alliance et de Royaume.',
      playerKingdom: 'Royaume #{kingdom}',
      playerAuthorityIntro:
        'Le rang d’Alliance, les rôles, les responsabilités du Royaume et l’accès aux Événements suivent le Gouverneur actif.',
      selectPlayer: 'Sélectionner le Gouverneur',
      playerAllianceTitle: 'Alliance du Gouverneur actif',
      playerAllianceIntro: 'L’accès à l’Alliance suit le rang et les rôles du Gouverneur actif.',
      noPlayerAllianceTitle: 'Ce Gouverneur n’est dans aucune Alliance',
      noPlayerAllianceIntro:
        'Changez de Gouverneur, rejoignez une Alliance ou créez une Alliance pour utiliser les fonctions d’Alliance.',
      openPlayerAlliance: 'Ouvrir l’Alliance',
      roles: 'Rôles d’Alliance',
      kingdomAlliances: 'Alliances du Royaume',
      transfers: 'Transfert de Royaume',
      kingdomSettings: 'Paramètres du Royaume',
      createTitle: 'Créer une Alliance',
      createIntro:
        'Créez une Alliance pour le Gouverneur actif. L’Alliance utilise le Royaume de ce Gouverneur, et le Gouverneur fondateur devient R5.',
      allianceName: 'Nom de l’Alliance',
      timezone: 'Fuseau horaire de l’Alliance',
      create: 'Créer l’Alliance',
    },
  },
} satisfies MessageCatalogue;

export default messages;
