<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Roster\Models\GovernorProgressionObservation;
use Illuminate\Support\Collection;

final class GovernorProgressionObservationQuery
{
    /** @return array{history:list<array<string,mixed>>,current:array<string,mixed>,last_updated_at:?string} */
    public function forRosterEntry(string $allianceId, string $rosterEntryId): array
    {
        $observations = $this->observations($allianceId, $rosterEntryId);
        $history = $observations
            ->reverse()
            ->values()
            ->map(fn (GovernorProgressionObservation $observation): array => $this->historyRow($observation))
            ->all();

        return [
            'history' => array_values($history),
            'current' => $this->project($observations),
            'last_updated_at' => $observations->isEmpty()
                ? null
                : $observations->last()->captured_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{before:array<string,mixed>,after:array<string,mixed>}
     */
    public function preview(
        string $allianceId,
        string $rosterEntryId,
        EvidenceKind $kind,
        array $payload,
        string $capturedAt,
        string $progressionDatasetId,
        string $progressionDatasetChecksum,
        string $evidenceId,
        string $reviewId,
    ): array {
        $observations = $this->observations($allianceId, $rosterEntryId);
        $before = $this->project($observations);
        $synthetic = new GovernorProgressionObservation;
        $synthetic->forceFill([
            'id' => 'preview-'.$reviewId,
            'alliance_id' => $allianceId,
            'roster_entry_id' => $rosterEntryId,
            'player_id' => 'preview',
            'kind' => $kind,
            'payload' => $payload,
            'captured_at' => $capturedAt,
            'progression_dataset_id' => $progressionDatasetId,
            'progression_dataset_checksum' => $progressionDatasetChecksum,
            'source' => 'screenshot_evidence',
            'evidence_id' => $evidenceId,
            'evidence_review_id' => $reviewId,
            'destination_idempotency_key' => str_repeat('0', 64),
            'accepted_by_player_id' => 'preview',
            'accepted_at' => now(),
        ]);
        $withPreview = $observations->push($synthetic)
            ->sortBy(static fn (GovernorProgressionObservation $observation): string => $observation->captured_at->format('Y-m-d\TH:i:s.u\Z').'|'.(string) $observation->id)
            ->values();

        return ['before' => $before, 'after' => $this->project($withPreview)];
    }

    /** @return Collection<int,GovernorProgressionObservation> */
    private function observations(string $allianceId, string $rosterEntryId): Collection
    {
        /** @var Collection<int,GovernorProgressionObservation> $observations */
        $observations = GovernorProgressionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $rosterEntryId)
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get();

        return $observations;
    }

    /**
     * @param  Collection<int,GovernorProgressionObservation>  $observations
     * @return array<string,mixed>
     */
    private function project(Collection $observations): array
    {
        $current = [
            'profile' => [],
            'heroes' => [],
            'governorGear' => [],
            'charms' => [],
            'completeRosterCapture' => null,
        ];
        foreach ($observations as $observation) {
            $payload = is_array($observation->payload) ? $observation->payload : [];
            $this->apply($current, $observation, $payload);
        }
        ksort($current['heroes']);
        foreach ($current['heroes'] as &$hero) {
            if (is_array($hero['facts'] ?? null)) {
                ksort($hero['facts']);
            }
            if (is_array($hero['gear'] ?? null)) {
                ksort($hero['gear']);
            }
        }
        unset($hero);
        ksort($current['governorGear']);
        ksort($current['charms']);
        ksort($current['profile']);

        return $current;
    }

    /**
     * @param  array<string,mixed>  $current
     * @param  array<string,mixed>  $payload
     */
    private function apply(array &$current, GovernorProgressionObservation $observation, array $payload): void
    {
        $kind = $observation->kind;
        if ($kind === EvidenceKind::GovernorProfile) {
            foreach (['observed_name', 'power', 'progression_level', 'observed_alliance_tag', 'kingdom_number'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $current['profile'][$field] = $this->fact($payload[$field], $observation);
                }
            }

            return;
        }
        if ($kind === EvidenceKind::GovernorHeroRoster) {
            $seen = [];
            foreach (is_array($payload['heroes'] ?? null) ? $payload['heroes'] : [] as $row) {
                if (! is_array($row) || ! is_string($row['hero_id'] ?? null)) {
                    continue;
                }
                $heroId = $row['hero_id'];
                $seen[$heroId] = true;
                $current['heroes'][$heroId] ??= ['facts' => [], 'gear' => [], 'membership' => null];
                $current['heroes'][$heroId]['membership'] = $this->fact('observed_present', $observation);
                foreach (['level', 'star', 'widget_level'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $current['heroes'][$heroId]['facts'][$field] = $this->fact($row[$field], $observation);
                    }
                }
            }
            if (($payload['complete_roster_capture'] ?? false) === true) {
                foreach (array_keys($current['heroes']) as $knownHeroId) {
                    if (! isset($seen[$knownHeroId])) {
                        $current['heroes'][$knownHeroId]['membership'] = $this->fact('observed_absent', $observation);
                    }
                }
                $current['completeRosterCapture'] = $this->fact(true, $observation);
            }

            return;
        }
        if ($kind === EvidenceKind::GovernorHeroDetail) {
            $heroId = is_string($payload['hero_id'] ?? null) ? $payload['hero_id'] : null;
            if ($heroId === null) {
                return;
            }
            $current['heroes'][$heroId] ??= ['facts' => [], 'gear' => [], 'membership' => null];
            $current['heroes'][$heroId]['membership'] = $this->fact('observed_present', $observation);
            foreach (['level', 'star', 'substar', 'widget_level'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $current['heroes'][$heroId]['facts'][$field] = $this->fact($payload[$field], $observation);
                }
            }

            return;
        }
        if ($kind === EvidenceKind::GovernorHeroGear) {
            $heroId = is_string($payload['hero_id'] ?? null) ? $payload['hero_id'] : null;
            if ($heroId === null) {
                return;
            }
            $current['heroes'][$heroId] ??= ['facts' => [], 'gear' => [], 'membership' => null];
            $current['heroes'][$heroId]['membership'] = $this->fact('observed_present', $observation);
            foreach (is_array($payload['gear'] ?? null) ? $payload['gear'] : [] as $row) {
                if (! is_array($row) || ! is_string($row['slot_id'] ?? null)) {
                    continue;
                }
                $slotId = $row['slot_id'];
                foreach (['quality', 'level', 'mastery_level'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $current['heroes'][$heroId]['gear'][$slotId][$field] = $this->fact($row[$field], $observation);
                    }
                }
                ksort($current['heroes'][$heroId]['gear'][$slotId]);
            }

            return;
        }
        if ($kind === EvidenceKind::GovernorGear) {
            foreach (is_array($payload['gear'] ?? null) ? $payload['gear'] : [] as $row) {
                if (! is_array($row) || ! is_string($row['slot_id'] ?? null)) {
                    continue;
                }
                $slotId = $row['slot_id'];
                foreach (['quality', 'level', 'star'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $current['governorGear'][$slotId][$field] = $this->fact($row[$field], $observation);
                    }
                }
                ksort($current['governorGear'][$slotId]);
            }

            return;
        }
        if ($kind === EvidenceKind::GovernorCharms) {
            foreach (is_array($payload['charms'] ?? null) ? $payload['charms'] : [] as $row) {
                if (! is_array($row) || ! is_string($row['slot_id'] ?? null)) {
                    continue;
                }
                $slotId = $row['slot_id'];
                foreach (['charm_id', 'level'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $current['charms'][$slotId][$field] = $this->fact($row[$field], $observation);
                    }
                }
                ksort($current['charms'][$slotId]);
            }
        }
    }

    /** @return array<string,mixed> */
    private function fact(mixed $value, GovernorProgressionObservation $observation): array
    {
        return [
            'value' => $value,
            'capturedAt' => $observation->captured_at->toIso8601String(),
            'observationId' => (string) $observation->id,
            'evidenceId' => (string) $observation->evidence_id,
            'reviewId' => (string) $observation->evidence_review_id,
            'datasetId' => (string) $observation->progression_dataset_id,
            'datasetChecksum' => (string) $observation->progression_dataset_checksum,
        ];
    }

    /** @return array<string,mixed> */
    private function historyRow(GovernorProgressionObservation $observation): array
    {
        return [
            'id' => (string) $observation->id,
            'kind' => $observation->kind->value,
            'payload' => is_array($observation->payload) ? $observation->payload : [],
            'capturedAt' => $observation->captured_at->toIso8601String(),
            'source' => (string) $observation->source,
            'evidenceId' => (string) $observation->evidence_id,
            'reviewId' => (string) $observation->evidence_review_id,
            'datasetId' => (string) $observation->progression_dataset_id,
            'datasetChecksum' => (string) $observation->progression_dataset_checksum,
            'acceptedAt' => $observation->accepted_at->toIso8601String(),
        ];
    }
}
