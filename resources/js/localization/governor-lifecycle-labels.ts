import type { LocaleCode } from './locales';
import type { MessageCatalogue } from './types';

const english: MessageCatalogue = {
  receipts: {
    'governor-created': 'Governor added to your account.',
    'governor-updated': 'Governor identity updated.',
    'governor-moved': 'Governor Kingdom updated.',
    'governor-released': 'Governor released from your account.',
  },
  common: {
    governors: 'Governors',
    manageGovernors: 'Manage Governors',
  },
  governorLifecycle: {
    eyebrow: 'Governor identity',
    title: 'My Governors',
    subtitle:
      'Manage the KingShot identities attached to this account. Authority always follows the active Governor, never the account as a whole.',
    dashboard: 'Dashboard',
    accountSettings: 'Account settings',
    identityRegistry: 'Identity registry',
    addGovernor: 'Add a Governor',
    addIntro:
      'Register a Governor you control. A stable game Player ID can be attached when known, but an existing identity cannot be silently claimed from another source.',
    governorName: 'Governor name',
    kingdom: 'Kingdom',
    gamePlayerId: 'Game Player ID',
    optional: 'optional',
    add: 'Add Governor',
    noGovernor: 'No Governor registered',
    startWithIdentity: 'Start with your KingShot identity',
    noGovernorIntro:
      'Your platform account is only the sign-in identity. Add a Governor above to establish the game-domain identity used for Kingdom, Alliance and Event authority.',
    ownedGovernors: 'Owned Governors',
    activeGovernor: 'Active Governor',
    noActiveAlliance: 'No active Alliance',
    useGovernor: 'Use this Governor',
    currentIdentity: 'Current identity',
    stableIdHelp: 'An established stable game Player ID cannot be replaced with a different ID.',
    saveIdentity: 'Save identity',
    kingdomIdentity: 'Kingdom identity',
    kingdomMoveHelp:
      'Kingdom changes fail closed while this Governor still has effective Kingdom roles, active Alliance membership, or conflicting roster state.',
    currentTargetKingdom: 'Current / target Kingdom',
    changeKingdom: 'Change Kingdom',
    identityHistory: 'Identity history',
    historyFrom: 'From',
    historyName: 'Name',
    historyKingdomId: 'Kingdom ID',
    historyGameId: 'Game ID',
    historySource: 'Source',
    historyReason: 'Reason',
    releaseTitle: 'Release from account',
    releasePreserves: 'Releasing ownership preserves the durable game identity and its history.',
    releaseGovernor: 'Release Governor',
    releaseConfirm:
      'Release {governor} from this account? The game identity and history will be preserved.',
  },
};

export function governorLifecycleLabels(locale: LocaleCode): MessageCatalogue {
  return locale === 'en' ? english : {};
}
