import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'Bear Hunt Debrief',
    eyebrow: 'Bear Hunt · After action',
    subtitle:
      'Review recorded damage, attendance, Rally participation, unresolved Governors, and how this Hunt compares with recent runs.',
    totalDamage: 'Total damage',
    governors: 'Governors',
    governor: 'Governor',
    governorCount: '{count} Governors',
    attendance: 'Attendance',
    recordedRallies: 'Recorded rallies',
    notRecorded: 'Not recorded',
    notComparable: 'No comparison available',
    noChange: 'No change from the previous Hunt',
    increased: 'increased',
    decreased: 'decreased',
    changeWithPercent: '{direction} by {amount} ({percent}%) vs previous Hunt',
    change: '{direction} by {amount} vs previous Hunt',
    rankUp: 'Up {count} ranks vs previous Hunt',
    rankDown: 'Down {count} ranks vs previous Hunt',
    yourHunt: 'Your Hunt',
    damage: 'Damage',
    rank: 'Rank',
    alliancePerformance: 'Alliance performance',
    leaderboard: 'Governor leaderboard',
    reportCount: '{count} recorded battle reports',
    unknownGovernor: 'Unknown Governor',
    noResults: 'No Governor damage has been recorded for this Hunt yet.',
    needsReview: 'Needs review',
    unmatchedGovernors: '{count} Governors need matching',
    reviewHelp:
      'Finish Governor matching in Screenshot Intake. The debrief never creates a second identity-matching workflow.',
    reviewImport: 'Review imported report',
    trends: 'Trends',
    runTrends: 'Recent Bear Hunt trends',
    yourDamageTrend: 'Your damage by Hunt',
    allianceDamageTrend: 'Alliance damage by Hunt',
    previousHunt: 'Previous Hunt',
    noPrevious: 'No previous Hunt',
    noPreviousHelp:
      'A comparison appears after an earlier completed Bear Hunt exists for this Alliance.',
    history: 'History',
    runHistory: 'Bear Hunt run history',
  },
} satisfies MessageCatalogue;

export default messages;
