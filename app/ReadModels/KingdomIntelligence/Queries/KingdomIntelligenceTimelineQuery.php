<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomIntelligence\Queries;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalFactory;
use App\ReadModels\Support\ReadModelTelemetry;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Chronological explanation layer over Intelligence-owned observations and
 * diplomacy history plus deterministic, non-persisted change signals.
 */
final readonly class KingdomIntelligenceTimelineQuery
{
    private const OWNER_LIMIT = 100;

    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private IntelligenceSignalFactory $signals,
    ) {}

    /** @return list<array<string,mixed>> */
    public function forTrackedAlliance(
        string $actorPlayerId,
        string $allianceId,
        string $trackingId,
    ): array {
        $startedAt = hrtime(true);
        if (! $this->authorization->allows(
            $actorPlayerId,
            $allianceId,
            IntelligencePermission::View,
        )) {
            throw new AuthorizationException;
        }

        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->whereNull('invalidated_at')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(self::OWNER_LIMIT)
            ->get();
        $transitions = KingdomAllianceDiplomacyTransition::query()
            ->where('alliance_id', $allianceId)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->limit(self::OWNER_LIMIT)
            ->get();

        $items = [];
        foreach ($observations as $observation) {
            $items[] = [
                'id' => 'observation:'.(string) $observation->id,
                'kind' => 'alliance_observation',
                'owner' => 'Intelligence/Observations',
                'observedAt' => $observation->captured_at->toIso8601String(),
                'source' => [
                    'type' => (string) $observation->source,
                    'reference' => $observation->source_record_id,
                    'adapter' => $observation->source_adapter_key,
                    'adapterVersion' => $observation->source_adapter_version,
                ],
                'evidenceIds' => [],
                'confidence' => null,
                'scope' => [
                    'type' => 'tracked_alliance',
                    'allianceId' => $allianceId,
                    'trackingId' => $trackingId,
                    'kingdomAllianceId' => (string) $observation->kingdom_alliance_id,
                ],
                'summary' => [
                    'name' => (string) $observation->observed_name,
                    'tag' => $observation->observed_tag,
                    'power' => $observation->power === null ? null : (string) $observation->power,
                    'memberCount' => $observation->member_count,
                ],
                'canonicalUrl' => '/alliance/kingdom-alliances/'.$trackingId.'/history',
                'derived' => false,
            ];
        }

        foreach ($transitions as $transition) {
            $items[] = [
                'id' => 'diplomacy:'.(string) $transition->id,
                'kind' => 'diplomacy_transition',
                'owner' => 'Intelligence/Diplomacy',
                'observedAt' => $transition->effective_at->toIso8601String(),
                'source' => [
                    'type' => 'officer_recorded',
                    'reference' => (string) $transition->id,
                    'adapter' => null,
                    'adapterVersion' => null,
                ],
                'evidenceIds' => [],
                'confidence' => null,
                'scope' => [
                    'type' => 'tracked_alliance',
                    'allianceId' => $allianceId,
                    'trackingId' => $trackingId,
                    'kingdomAllianceId' => (string) $transition->kingdom_alliance_id,
                ],
                'summary' => [
                    'from' => $transition->from_state->value,
                    'to' => $transition->to_state->value,
                ],
                'canonicalUrl' => '/alliance/kingdom-alliances/'.$trackingId.'/diplomacy',
                'derived' => false,
            ];
        }

        $accepted = $observations->values();
        for ($index = 0; $index + 1 < $accepted->count(); $index++) {
            $current = $accepted->get($index);
            $previous = $accepted->get($index + 1);
            if (! $current instanceof KingdomAllianceObservation
                || ! $previous instanceof KingdomAllianceObservation) {
                continue;
            }

            foreach ($this->signals->allianceObservationChanges($current, $previous, now()) as $signal) {
                $items[] = [
                    'id' => 'signal:'.$signal->fingerprint,
                    'kind' => $signal->type->value,
                    'owner' => $signal->sourceOwner,
                    'observedAt' => $signal->observedAt,
                    'source' => [
                        'type' => $signal->sourceClassification,
                        'reference' => implode(',', $signal->sourceRecordIds),
                        'adapter' => null,
                        'adapterVersion' => $signal->ruleVersion,
                    ],
                    'evidenceIds' => $signal->evidenceIds,
                    'confidence' => null,
                    'scope' => [
                        'type' => $signal->subjectType,
                        'allianceId' => $allianceId,
                        'trackingId' => $trackingId,
                        'kingdomAllianceId' => null,
                    ],
                    'summary' => [
                        'text' => $signal->summary,
                        'metric' => $signal->metric,
                        'currentValue' => $signal->currentValue,
                        'previousValue' => $signal->previousValue,
                        'delta' => $signal->delta,
                    ],
                    'canonicalUrl' => $signal->canonicalUrl,
                    'derived' => true,
                ];
            }
        }

        usort($items, static function (array $left, array $right): int {
            $date = strcmp((string) $right['observedAt'], (string) $left['observedAt']);

            return $date !== 0 ? $date : strcmp((string) $left['id'], (string) $right['id']);
        });

        $projection = array_slice($items, 0, 200);
        ReadModelTelemetry::record('intelligence_timeline.rendered', $startedAt, [
            'actor_player_id' => $actorPlayerId,
            'alliance_id' => $allianceId,
            'tracking_id' => $trackingId,
        ], [
            'observation_count' => $observations->count(),
            'transition_count' => $transitions->count(),
            'item_count' => count($projection),
        ], array_values(array_unique(array_map(
            static fn (array $item): string => (string) $item['kind'],
            $projection,
        ))));

        return $projection;
    }
}
