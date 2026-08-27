import type { LocaleCode } from './locales';
import type { MessageCatalogue } from './types';

const english = {
  progression: {
    goalPlanner: 'Progression Goal Planner',
    goalPlannerHelp:
      'Compare an authorized observed state with a factual target from one immutable dataset. Resource totals appear only when that progression family has passed its calculator evidence gate.',
    selectGoal: 'Choose a factual goal',
    currentToTarget: 'Current observed state → target',
    goalFamily: 'Progression family',
    selectGoalFamily: 'Select a progression family',
    goalSubject: 'Subject',
    selectGoalSubject: 'Select a subject',
    targetState: 'Target factual state',
    selectTargetState: 'Select a target',
    noDeterministicStates:
      'This subject does not have a deterministic state ladder in the pinned dataset. No progression distance or calculation will be inferred.',
    observationDataset: 'Observation normalized with',
    currentStateUnknown: 'Current state is unknown',
    factualTarget: 'Factual target',
    targetPinnedHelp: 'Target resolved from immutable dataset {version}.',
    stepsRemaining: '{count} progression transitions remaining',
    progressionPath: 'Factual progression path',
    chooseTargetHelp: 'Choose a factual target to compare it with the observed current state.',
    prerequisites: 'Prerequisites',
    sourcedPrerequisites: 'Sourced prerequisite facts',
    prerequisiteUnknown: 'observed satisfaction unknown',
    calculatorEvidence: 'Calculator evidence gate',
    calculatorStatus: 'Resource calculation status',
    noCalculatorProgram:
      'This planning family has no approved calculator program. Factual goal planning remains available.',
    calculateResources: 'Calculate resource delta',
    calculationResult: 'Evidence-backed calculation',
    resourceRequirements: 'Resource requirements',
    calculationUnavailable: 'Calculation unavailable',
    calculationVersion: 'Calculation version',
    transitionsIncluded: 'Transitions included',
    calculatorState: {
      calculator_ready: 'Calculator ready',
      qualified_pending_implementation: 'Evidence qualified — implementation pending',
      evidence_review: 'Evidence under review',
      evidence_incomplete: 'Evidence incomplete',
      source_gap: 'Source gap',
      evidence_conflict: 'Evidence conflict',
      unsupported: 'Calculator unsupported',
    },
  },
} satisfies MessageCatalogue;

const french = {
  progression: {
    goalPlanner: 'Planificateur d’objectifs de progression',
    goalPlannerHelp:
      'Comparez un état observé autorisé à une cible factuelle provenant d’un jeu de données immuable. Les totaux de ressources apparaissent uniquement lorsque cette famille de progression a franchi le contrôle des preuves du calculateur.',
    selectGoal: 'Choisir un objectif factuel',
    currentToTarget: 'État observé actuel → cible',
    goalFamily: 'Famille de progression',
    selectGoalFamily: 'Sélectionner une famille de progression',
    goalSubject: 'Sujet',
    selectGoalSubject: 'Sélectionner un sujet',
    targetState: 'État factuel cible',
    selectTargetState: 'Sélectionner une cible',
    noDeterministicStates:
      'Ce sujet ne possède pas d’échelle d’états déterministe dans le jeu de données épinglé. Aucune distance de progression ni aucun calcul ne sera déduit.',
    observationDataset: 'Observation normalisée avec',
    currentStateUnknown: 'État actuel inconnu',
    factualTarget: 'Cible factuelle',
    targetPinnedHelp: 'Cible résolue à partir du jeu de données immuable {version}.',
    stepsRemaining: '{count} transitions de progression restantes',
    progressionPath: 'Chemin de progression factuel',
    chooseTargetHelp: 'Choisissez une cible factuelle à comparer à l’état observé actuel.',
    prerequisites: 'Prérequis',
    sourcedPrerequisites: 'Prérequis factuels sourcés',
    prerequisiteUnknown: 'satisfaction observée inconnue',
    calculatorEvidence: 'Contrôle des preuves du calculateur',
    calculatorStatus: 'État du calcul des ressources',
    noCalculatorProgram:
      'Cette famille de planification ne possède aucun calculateur approuvé. La planification factuelle demeure disponible.',
    calculateResources: 'Calculer l’écart de ressources',
    calculationResult: 'Calcul fondé sur des preuves',
    resourceRequirements: 'Ressources requises',
    calculationUnavailable: 'Calcul indisponible',
    calculationVersion: 'Version du calcul',
    transitionsIncluded: 'Transitions incluses',
    calculatorState: {
      calculator_ready: 'Calculateur prêt',
      qualified_pending_implementation: 'Preuves qualifiées — implémentation en attente',
      evidence_review: 'Preuves en révision',
      evidence_incomplete: 'Preuves incomplètes',
      source_gap: 'Lacune de source',
      evidence_conflict: 'Conflit de preuves',
      unsupported: 'Calculateur non pris en charge',
    },
  },
} satisfies MessageCatalogue;

export function progressionPlannerLabels(locale: LocaleCode): MessageCatalogue {
  return locale === 'fr' ? french : english;
}
