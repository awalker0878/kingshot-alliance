from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    target = Path(path)
    text = target.read_text()
    if old not in text:
        raise SystemExit(f"expected source shape not found in {path}: {old[:100]!r}")
    if text.count(old) != 1:
        raise SystemExit(f"expected one source match in {path}, found {text.count(old)}")
    target.write_text(text.replace(old, new, 1))


# Delegate the established validator through the new dataset-topology validator for
# structured Building/Academy/War Academy screenshots. Existing schemas stay on
# their proven validation methods.
replace_once(
    'app/Contexts/Intelligence/Roster/Services/GovernorProgressionObservationValidator.php',
    '    public function __construct(private ProgressionDatasetQuery $progression) {}',
    '''    public function __construct(\n        private ProgressionDatasetQuery $progression,\n        private StructuredGovernorProgressionObservationValidator $structured,\n    ) {}''',
)
replace_once(
    'app/Contexts/Intelligence/Roster/Services/GovernorProgressionObservationValidator.php',
    '''        if (! $kind->isGovernorProgression()) {\n            throw ValidationException::withMessages(['kind' => 'The observation kind is not Governor Progression Evidence.']);\n        }\n        $dataset = $this->progression->require($datasetId, $datasetChecksum);''',
    '''        if (! $kind->isGovernorProgression()) {\n            throw ValidationException::withMessages(['kind' => 'The observation kind is not Governor Progression Evidence.']);\n        }\n        if ($this->structured->supports($kind)) {\n            return $this->structured->validate($kind, $payload, $datasetId, $datasetChecksum);\n        }\n        $dataset = $this->progression->require($datasetId, $datasetChecksum);''',
)

# Review validation must use exactly the same destination validation contract.
replace_once(
    'app/Contexts/Intelligence/Evidence/Actions/SaveGovernorProgressionEvidenceReview.php',
    'use App\\Contexts\\Intelligence\\Roster\\Services\\GovernorProgressionObservationValidator;',
    '''use App\\Contexts\\Intelligence\\Roster\\Services\\GovernorProgressionObservationValidator;\nuse App\\Contexts\\Intelligence\\Roster\\Services\\StructuredGovernorProgressionObservationValidator;''',
)
replace_once(
    'app/Contexts/Intelligence/Evidence/Actions/SaveGovernorProgressionEvidenceReview.php',
    '        private GovernorProgressionObservationValidator $validator,',
    '''        private GovernorProgressionObservationValidator $validator,\n        private StructuredGovernorProgressionObservationValidator $structuredValidator,''',
)
replace_once(
    'app/Contexts/Intelligence/Evidence/Actions/SaveGovernorProgressionEvidenceReview.php',
    '            $reviewedPayload = $this->validator->validate($kind, $payload, $datasetId, $datasetChecksum);',
    '''            $reviewedPayload = $this->structuredValidator->supports($kind)\n                ? $this->structuredValidator->validate($kind, $payload, $datasetId, $datasetChecksum)\n                : $this->validator->validate($kind, $payload, $datasetId, $datasetChecksum);''',
)

# Route approved structured reviews into the common append-only observation writer.
commit_path = 'app/Contexts/Intelligence/Evidence/Actions/CommitReviewedGovernorProgressionEvidence.php'
replace_once(
    commit_path,
    'use App\\Contexts\\Intelligence\\Roster\\Actions\\RecordHeroRosterEvidence;',
    '''use App\\Contexts\\Intelligence\\Roster\\Actions\\RecordHeroRosterEvidence;\nuse App\\Contexts\\Intelligence\\Roster\\Actions\\RecordStructuredProgressionEvidence;''',
)
replace_once(
    commit_path,
    '        private RecordGovernorCharmsEvidence $governorCharms,',
    '''        private RecordGovernorCharmsEvidence $governorCharms,\n        private RecordStructuredProgressionEvidence $structuredProgression,''',
)
replace_once(
    commit_path,
    '''            EvidenceKind::GovernorCharms => $this->governorCharms->handle(...$arguments),\n            default => throw new LogicException('Unsupported Governor Progression Evidence destination schema.'),''',
    '''            EvidenceKind::GovernorCharms => $this->governorCharms->handle(...$arguments),\n            EvidenceKind::GovernorBuildings,\n            EvidenceKind::GovernorAcademyResearch,\n            EvidenceKind::GovernorWarAcademyResearch => $this->structuredProgression->handle($kind, ...$arguments),\n            default => throw new LogicException('Unsupported Governor Progression Evidence destination schema.'),''',
)
replace_once(
    commit_path,
    '''            EvidenceKind::GovernorCharms => 'RecordGovernorCharmsEvidence',\n            default => throw new LogicException('Unsupported Governor Progression Evidence destination schema.'),''',
    '''            EvidenceKind::GovernorCharms => 'RecordGovernorCharmsEvidence',\n            EvidenceKind::GovernorBuildings,\n            EvidenceKind::GovernorAcademyResearch,\n            EvidenceKind::GovernorWarAcademyResearch => 'RecordStructuredProgressionEvidence',\n            default => throw new LogicException('Unsupported Governor Progression Evidence destination schema.'),''',
)

# Every read model must expose the same complete projection shape, including when
# Alliance observations are unavailable.
fallback_old = "['history' => [], 'current' => ['profile' => [], 'heroes' => [], 'governorGear' => [], 'charms' => [], 'completeRosterCapture' => null], 'last_updated_at' => null]"
fallback_new = "['history' => [], 'current' => ['profile' => [], 'heroes' => [], 'governorGear' => [], 'charms' => [], 'buildings' => [], 'academyResearch' => [], 'warAcademyResearch' => [], 'completeRosterCapture' => null], 'last_updated_at' => null]"
replace_once(
    'app/ReadModels/Progression/Http/Controllers/GovernorProgressionController.php',
    fallback_old,
    fallback_new,
)

planner_path = 'app/ReadModels/Progression/Http/Controllers/ProgressionPlannerController.php'
replace_once(
    planner_path,
    "                'current' => ['profile' => [], 'heroes' => [], 'governorGear' => [], 'charms' => [], 'completeRosterCapture' => null],",
    "                'current' => ['profile' => [], 'heroes' => [], 'governorGear' => [], 'charms' => [], 'buildings' => [], 'academyResearch' => [], 'warAcademyResearch' => [], 'completeRosterCapture' => null],",
)

print('Progression backend completion edits applied successfully.')
