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
    currentAlliance: 'Alliance actuelle',
    noActiveAlliance: 'Sélectionnez une alliance pour ouvrir les outils d’alliance.',
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
      activeAllianceTitle: 'Alliance active',
      activeAllianceIntro: 'Cette alliance est le contexte actuel des outils liés à l’alliance.',
      noActiveAllianceTitle: 'Choisissez une alliance active',
      noActiveAllianceIntro:
        'Sélectionnez une de vos adhésions ci-dessous avant d’ouvrir les outils d’alliance, d’événements, de roster, de royaume ou de transfert.',
      alliancesTitle: 'Vos alliances',
      alliancesIntro: 'Choisissez l’alliance à utiliser comme contexte de travail actuel.',
      openActiveAlliance: 'Ouvrir l’alliance active',
      roles: 'Rôles',
      noRoles: 'Aucun rôle attribué',
      switchAlliance: 'Passer à cette alliance',
      kingdomAlliances: 'Alliances du royaume',
      transfers: 'Transferts',
      kingdomSettings: 'Paramètres du royaume',
      empty:
        'Vous n’avez pas encore d’adhésion active à une alliance. Créez une alliance ci-dessous pour établir votre premier contexte.',
      createTitle: 'Créer une alliance',
      createIntro:
        'Créez une nouvelle alliance et devenez son propriétaire initial en une seule opération.',
      allianceName: 'Nom de l’alliance',
      kingdomNumber: 'Numéro du royaume',
      timezone: 'Fuseau horaire',
      create: 'Créer l’alliance',
    },
  },
} satisfies MessageCatalogue;

export default messages;
