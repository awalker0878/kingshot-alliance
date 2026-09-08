<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Services;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionTopologyQuery;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use Illuminate\Validation\ValidationException;

final readonly class StructuredGovernorProgressionObservationValidator
{
    public function __construct(
        private ProgressionDatasetQuery $datasets,
        private ProgressionTopologyQuery $topology,
    ) {}

    public function supports(EvidenceKind $kind): bool
    {
        return $kind->isStructuredGovernorProgression();
    }

    /** @param array<string,mixed> $payload
     *  @return array{states:list<array{subject_id:string,state_id:string,level:int}>}
     */
    public function validate(
        EvidenceKind $kind,
        array $payload,
        string $datasetId,
        string $datasetChecksum,
    ): array {
        if (! $this->supports($kind)) {
            throw ValidationException::withMessages(['kind' => 'Unsupported structured Governor Progression observation kind.']);
        }
        if (array_diff(array_keys($payload), ['states']) !== []) {
            throw ValidationException::withMessages(['payload' => 'Structured progression observations accept only the states collection.']);
        }
        $rows = $payload['states'] ?? null;
        if (! is_array($rows) || $rows === [] || count($rows) > 250) {
            throw ValidationException::withMessages(['payload.states' => 'A structured progression observation must contain 1-250 reviewed states.']);
        }

        $dataset = $this->datasets->require($datasetId, $datasetChecksum);
        $family = $this->family($kind);
        $subjects = $this->topology->subjects($dataset, $family);
        $validated = [];
        $seen = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row) || array_diff(array_keys($row), ['subject_id', 'level', 'state_id']) !== []) {
                throw ValidationException::withMessages(["payload.states.$index" => 'A reviewed progression state row is invalid.']);
            }
            $subjectInput = $row['subject_id'] ?? null;
            if (! is_string($subjectInput) || trim($subjectInput) === '') {
                throw ValidationException::withMessages(["payload.states.$index.subject_id" => 'A reviewed progression subject is required.']);
            }
            $subjectId = $this->canonicalSubjectId($subjectInput, $subjects);
            if ($subjectId === null) {
                throw ValidationException::withMessages(["payload.states.$index.subject_id" => 'The reviewed progression subject does not exist in the pinned dataset.']);
            }
            if (isset($seen[$subjectId])) {
                throw ValidationException::withMessages(["payload.states.$index.subject_id" => 'A progression subject may appear only once in one observation.']);
            }
            $seen[$subjectId] = true;

            $level = filter_var($row['level'] ?? null, FILTER_VALIDATE_INT);
            if ($level === false || $level < 0) {
                throw ValidationException::withMessages(["payload.states.$index.level" => 'The reviewed progression level must be a non-negative integer.']);
            }
            $stateId = is_string($row['state_id'] ?? null) && trim($row['state_id']) !== ''
                ? trim($row['state_id'])
                : 'level:'.$level;
            $states = $this->topology->states($dataset, $family, $subjectId);
            $state = $this->state($states, $stateId);
            if ($state === null || (int) ($state['attributes']['level'] ?? -1) !== $level) {
                throw ValidationException::withMessages(["payload.states.$index.level" => 'The reviewed progression level cannot be resolved in the pinned dataset.']);
            }

            $validated[] = ['subject_id' => $subjectId, 'state_id' => $stateId, 'level' => $level];
        }
        usort($validated, static fn (array $a, array $b): int => strcmp($a['subject_id'], $b['subject_id']));

        return ['states' => $validated];
    }

    private function family(EvidenceKind $kind): string
    {
        return match ($kind) {
            EvidenceKind::GovernorBuildings => 'buildings',
            EvidenceKind::GovernorAcademyResearch => 'academy_research',
            EvidenceKind::GovernorWarAcademyResearch => 'war_academy_research',
            default => throw ValidationException::withMessages(['kind' => 'Unsupported structured progression family.']),
        };
    }

    /** @param list<array{id:string,label:string,context:array<string,mixed>}> $subjects */
    private function canonicalSubjectId(string $value, array $subjects): ?string
    {
        $needle = mb_strtolower(trim($value));
        foreach ($subjects as $subject) {
            $id = mb_strtolower((string) ($subject['id'] ?? ''));
            $label = mb_strtolower((string) ($subject['label'] ?? ''));
            if ($needle === $id || $needle === $label) {
                return (string) $subject['id'];
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $states
     *  @return array<string,mixed>|null
     */
    private function state(array $states, string $stateId): ?array
    {
        foreach ($states as $state) {
            if (($state['id'] ?? null) === $stateId) {
                return $state;
            }
        }

        return null;
    }
}
