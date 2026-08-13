import type { LocaleCode } from '../locales';
import { accountExperienceMessages } from './account-extra';
import { allianceOperationsMessages } from './alliance-operations';
import { applicationExtraMessages } from './app-extra';
import { authExtraMessages } from './auth-extra';
import { additionalCatalogues } from './catalogues';
import { contentMessages } from './content-experience';
import { contributionLocaleOverrides2 } from './contribution-extra-2';
import { contributionLocaleOverrides3 } from './contribution-extra-3';
import { contributionLocaleOverrides4 } from './contribution-extra-4';
import { contributionLocaleOverrides } from './contribution-extra';
import en from './en';
import { eventCoordinatorMessages } from './event-coordinator';
import { eventDetailMessages } from './event-detail';
import { integrationMessages } from './integration-experience';
import { kingdomP7AMessages } from './kingdom-p7a';
import { kingdomP7BMessages } from './kingdom-p7b';
import { kingdomP7CMessages } from './kingdom-p7c';
import { kingdomP7DMessages } from './kingdom-p7d';
import { publicExtraMessages } from './public-extra';
import { publicMessages } from './public';
import { recruitmentMessages } from './recruitment';
import { rosterManagementMessages } from './roster-management';
import { rosterMessages } from './roster';
import { rosterWorkflowOverrides } from './roster-workflow-overrides';
import { rosterWorkflowMessages } from './roster-workflows';

type StringLeaves<T> = {
  [K in keyof T]: T[K] extends string ? string : T[K] extends object ? StringLeaves<T[K]> : never;
};

const contributionCopy = {
  eyebrow: 'Alliance contributions',
  title: 'Contributions & progress',
  subtitle:
    'Recorded facts, approved progress, corrections, and calculation details for {alliance}.',
  manageReporting: 'Manage reporting',
  overview: 'Contributions overview',
  activeCategories: 'Active categories',
  categoriesWithGoals: 'Categories with goals',
  selfReportCategories: 'Self-report categories',
  recentRecordsShown: 'Recent records shown',
  progress: 'Progress',
  currentPeriods: 'Current category periods',
  goal: 'Goal',
  noGoal: 'No goal',
  notConfigured: 'Not configured',
  version: 'Version',
  evidenceRequired: 'Evidence is required for this category.',
  noCategories: 'No active contribution categories are configured.',
  history: 'History',
  yourHistory: 'Your contribution history',
  historyHelp: 'Newest records first. Historical corrections and reversals remain visible.',
  category: 'Category',
  value: 'Value',
  status: 'Status',
  source: 'Source',
  recorded: 'Recorded',
  dataClass: 'Data class',
  explanation: 'Explanation',
  correctedReason: 'Corrected: {reason}',
  reversedReason: 'Reversed: {reason}',
  calculationVersion: 'Calculation v{version}',
  noRecords: 'No contribution records yet.',
  selfReport: 'Self-report',
  selfReportTitle: 'Submit a contribution',
  selfReportHelp: 'Self-reported records remain pending until an authorized leader approves them.',
  selectCategory: 'Select category',
  evidenceNote: 'Evidence or note',
  submitApproval: 'Submit for approval',
  leaderboards: 'Leaderboards',
  approvedCategoryLeaderboards: 'Approved category leaderboards',
  noApprovedRecords: 'No approved records for this period.',
  memberView: 'Member view',
  managerTitle: 'Contribution reporting',
  managerSubtitle:
    'Explainable records, attendance reconciliation, data quality, exports, and scheduled reporting for {alliance}.',
  exportCsv: 'CSV export',
  exportSpreadsheet: 'Spreadsheet export',
  operationalMetrics: 'Operational reporting metrics',
  activeMembers: 'Active members',
  memberMovement30: '+{joined} / -{left} in 30 days',
  attendance: 'Attendance',
  attendanceBreakdown: '{attended} attended · {noShows} no-show',
  recruitment: 'Recruitment',
  recruitmentBreakdown: '{joined} joined / {total} candidates',
  pendingApprovals: 'Pending approvals',
  dataIssues: 'Data issues',
  configuration: 'Configuration',
  newCategory: 'New contribution category',
  name: 'Name',
  unit: 'Unit',
  period: 'Period',
  goalPerMember: 'Goal per member',
  periodStart: 'Period start',
  periodEnd: 'Period end',
  description: 'Description',
  calculationKey: 'Calculation key',
  calculationVersionField: 'Calculation version',
  calculationExplanation: 'Calculation explanation',
  allowSelfReport: 'Allow self-report',
  leaderboardEnabled: 'Leaderboard enabled',
  createCategory: 'Create category',
  manualContribution: 'Manual contribution',
  member: 'Member',
  selectMember: 'Select member',
  recordPending: 'Record pending contribution',
  attendanceQuality: 'Attendance & data quality',
  attendanceQualityHelp:
    'Derived records use the category calculation version; refreshing flags never changes contribution totals.',
  reconcileAttendance: 'Reconcile attendance',
  refreshQuality: 'Refresh data quality',
  resolve: 'Resolve',
  noFlags: 'No open data-quality flags.',
  approvalQueue: 'Approval queue',
  actions: 'Actions',
  approve: 'Approve',
  correct: 'Correct',
  reverse: 'Reverse',
  correctValuePrompt: 'Correct {member} {category} value:',
  correctionReasonPrompt: 'Why is this correction required?',
  reverseReasonPrompt: 'Why should this record be reversed?',
  noPending: 'No pending approvals.',
  scheduledReport: 'Scheduled report',
  scheduleHelp: 'Schedules queue versioned report requests through the notification outbox.',
  recipient: 'Recipient',
  cadence: 'Cadence',
  timezone: 'Time zone',
  firstDelivery: 'First delivery',
  createSchedule: 'Create schedule',
  reportHistory: 'Report history',
  reportVersionRows: 'Version {version} · {rows} rows',
  queued: 'Queued',
  noReportRuns: 'No report runs yet.',
  configuredCategories: 'Configured categories',
  approvedTotal: 'Approved total',
  leaderboardOptOut: 'Leaderboard opted out',
  inactive: 'Inactive',
  recentRecords: 'Recent records',
  recentRecordsHelp: 'Latest recorded contribution facts and state changes.',
  noRecentRecords: 'No recent contribution records.',
  reportSchedules: 'Report schedules',
  nextDue: 'Next due',
  lastQueued: 'Last queued',
  enabled: 'Enabled',
  disabled: 'Disabled',
  noSchedules: 'No report schedules configured.',
  categoryLeaderboards: 'Category leaderboards',
  calculationDetails: 'Calculation details',
  noLeaderboards: 'No category leaderboards are enabled.',
  evidence: 'Evidence',
  required: 'Required',
  optional: 'Optional',
  selfReportAllowed: 'Self-report allowed',
  leaderboardOn: 'Leaderboard on',
  leaderboardOff: 'Leaderboard off',
} as const;

const contributionOverrides = {
  ...contributionLocaleOverrides,
  ...contributionLocaleOverrides2,
  ...contributionLocaleOverrides3,
  ...contributionLocaleOverrides4,
};

type BaseMessageTree = StringLeaves<typeof en>;
type ContentMessageTree = (typeof contentMessages)['en'];
type IntegrationMessageTree = (typeof integrationMessages)['en'];
type KingdomP7AMessageTree = (typeof kingdomP7AMessages)['en'];
type KingdomP7BMessageTree = (typeof kingdomP7BMessages)['en'];
type KingdomP7CMessageTree = (typeof kingdomP7CMessages)['en'];
type KingdomP7DMessageTree = (typeof kingdomP7DMessages)['en'];
type AccountExperienceMessageTree = (typeof accountExperienceMessages)['en'];
type AllianceOperationsMessageTree = (typeof allianceOperationsMessages)['en'];
type ApplicationExtraMessageTree = (typeof applicationExtraMessages)['en'];
type AuthExtraMessageTree = (typeof authExtraMessages)['en'];
type EventCoordinatorMessageTree = (typeof eventCoordinatorMessages)['en'];
type EventDetailMessageTree = (typeof eventDetailMessages)['en'];
type PublicMessageTree = (typeof publicMessages)['en'];
type PublicExtraMessageTree = (typeof publicExtraMessages)['en'];
type RecruitmentMessageTree = (typeof recruitmentMessages)['en'];
type RosterManagementMessageTree = (typeof rosterManagementMessages)['en'];
type RosterMessageTree = (typeof rosterMessages)['en'];
type RosterWorkflowMessageTree = (typeof rosterWorkflowMessages)['en'];
type ContributionMessageTree = { contributions: { [K in keyof typeof contributionCopy]: string } };

export type MessageTree = BaseMessageTree &
  ContentMessageTree &
  IntegrationMessageTree &
  KingdomP7AMessageTree &
  KingdomP7BMessageTree &
  KingdomP7CMessageTree &
  KingdomP7DMessageTree &
  AccountExperienceMessageTree &
  AllianceOperationsMessageTree &
  ApplicationExtraMessageTree &
  AuthExtraMessageTree &
  EventCoordinatorMessageTree &
  EventDetailMessageTree &
  PublicMessageTree &
  PublicExtraMessageTree &
  RecruitmentMessageTree &
  RosterManagementMessageTree &
  RosterMessageTree &
  RosterWorkflowMessageTree &
  ContributionMessageTree;

const baseMessages: Record<LocaleCode, BaseMessageTree> = {
  en,
  ...additionalCatalogues,
};

function catalogue(locale: LocaleCode) {
  return {
    ...baseMessages[locale],
    ...contentMessages[locale],
    ...integrationMessages[locale],
    ...kingdomP7AMessages[locale],
    ...kingdomP7BMessages[locale],
    ...kingdomP7CMessages[locale],
    ...kingdomP7DMessages[locale],
    ...accountExperienceMessages[locale],
    ...allianceOperationsMessages[locale],
    ...applicationExtraMessages[locale],
    ...authExtraMessages[locale],
    ...eventCoordinatorMessages[locale],
    ...eventDetailMessages[locale],
    ...publicMessages[locale],
    ...publicExtraMessages[locale],
    ...recruitmentMessages[locale],
    ...rosterManagementMessages[locale],
    ...rosterMessages[locale],
    ...rosterWorkflowMessages[locale],
    ...(rosterWorkflowOverrides[locale] ?? {}),
    contributions: { ...contributionCopy, ...(contributionOverrides[locale] ?? {}) },
  };
}

export const messages: Record<LocaleCode, MessageTree> = Object.fromEntries(
  (Object.keys(baseMessages) as LocaleCode[]).map((locale) => [locale, catalogue(locale)]),
) as Record<LocaleCode, MessageTree>;

export function hasMessageCatalogue(locale: LocaleCode): boolean {
  return Object.prototype.hasOwnProperty.call(messages, locale);
}
