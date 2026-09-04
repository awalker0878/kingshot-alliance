<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Queries;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidenceReview;
use App\Contexts\Intelligence\Roster\Models\AllianceRosterObservationBatch;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;

final readonly class AllianceRosterReconciliationQuery
{
    public function __construct(private PlayerReferenceQuery $players) {}

    /** @return array<string,mixed> */
    public function forAlliance(string $allianceId): array
    {
        $batch = AllianceRosterObservationBatch::query()
            ->where('alliance_id', $allianceId)
            ->latest('captured_at')
            ->with('observations')
            ->first();

        if (! $batch instanceof AllianceRosterObservationBatch) {
            return [
                'batch' => null,
                'items' => [],
                'summary' => ['needsReview' => 0, 'matched' => 0],
            ];
        }

        $review = AllianceRosterEvidenceReview::query()->whereKey((string) $batch->source_review_id)->first();
        $completeRoster = (bool) (($review->payload ?? [])['complete_roster'] ?? false);

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->get();
        $playerIds = $memberships->pluck('player_id')->map(static fn ($id): string => (string) $id)->all();
        $playerRefs = $this->players->byIds($playerIds);
        $entries = AllianceRosterEntry::query()->where('alliance_id', $allianceId)->get();
        $entriesById = $entries->keyBy(static fn (AllianceRosterEntry $entry): string => (string) $entry->id);
        $entryByPlayer = $entries->keyBy(static fn (AllianceRosterEntry $entry): string => (string) $entry->player_id);
        $membershipByPlayer = $memberships->keyBy(static fn (AllianceMembership $membership): string => (string) $membership->player_id);

        $gameIdToPlayer = [];
        $nameToPlayers = [];
        foreach ($playerRefs as $playerId => $ref) {
            if ($ref->gamePlayerId !== null && $ref->gamePlayerId !== '') {
                $gameIdToPlayer[(string) $ref->gamePlayerId] = (string) $playerId;
            }
            $nameToPlayers[mb_strtolower(trim($ref->currentName))][] = (string) $playerId;
        }

        $latestPowerByEntry = [];
        $entryIds = $entries->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        if ($entryIds !== []) {
            foreach (PlayerSnapshot::query()->where('alliance_id', $allianceId)->whereIn('roster_entry_id', $entryIds)->orderByDesc('captured_at')->get() as $snapshot) {
                $entryId = (string) $snapshot->roster_entry_id;
                $latestPowerByEntry[$entryId] ??= (int) $snapshot->power;
            }
        }

        $items = [];
        $matchedPlayers = [];
        foreach ($batch->observations as $observation) {
            $matchedPlayerId = null;
            $identityState = 'unmatched';
            if ($observation->roster_entry_id !== null) {
                $entry = $entriesById->get((string) $observation->roster_entry_id);
                if ($entry instanceof AllianceRosterEntry) {
                    $matchedPlayerId = (string) $entry->player_id;
                    $identityState = 'linked';
                }
            }
            if ($matchedPlayerId === null && $observation->game_player_id !== null) {
                $matchedPlayerId = $gameIdToPlayer[(string) $observation->game_player_id] ?? null;
                if ($matchedPlayerId !== null) {
                    $identityState = 'game_id';
                }
            }
            if ($matchedPlayerId === null) {
                $matches = $nameToPlayers[mb_strtolower(trim((string) $observation->observed_name))] ?? [];
                if (count($matches) === 1) {
                    $matchedPlayerId = $matches[0];
                    $identityState = 'name';
                } elseif (count($matches) > 1) {
                    $identityState = 'ambiguous';
                }
            }

            $reasons = [];
            $membership = $matchedPlayerId === null ? null : $membershipByPlayer->get($matchedPlayerId);
            $entry = $matchedPlayerId === null ? null : $entryByPlayer->get($matchedPlayerId);
            if ($identityState === 'ambiguous') {
                $reasons[] = 'ambiguous_identity';
            } elseif (! $membership instanceof AllianceMembership) {
                $reasons[] = 'observation_without_membership';
                $reasons[] = 'observed_new';
            } else {
                $matchedPlayers[$matchedPlayerId] = true;
                $ref = $playerRefs[$matchedPlayerId] ?? null;
                if ($ref !== null && mb_strtolower(trim($ref->currentName)) !== mb_strtolower(trim((string) $observation->observed_name))) {
                    $reasons[] = 'name_changed';
                }
                if ($observation->observed_rank !== null && strtolower((string) $membership->rank->value) !== strtolower((string) $observation->observed_rank)) {
                    $reasons[] = 'rank_changed';
                }
                if ($observation->power !== null && $entry instanceof AllianceRosterEntry) {
                    $baselinePower = $latestPowerByEntry[(string) $entry->id] ?? null;
                    if ($baselinePower !== null && $baselinePower !== (int) $observation->power) {
                        $reasons[] = 'power_changed';
                    }
                }
                if ($reasons === []) {
                    $reasons[] = 'matches_membership';
                }
            }

            $items[] = [
                'observationId' => (string) $observation->id,
                'observedName' => (string) $observation->observed_name,
                'gamePlayerId' => $observation->game_player_id,
                'observedRank' => $observation->observed_rank,
                'power' => $observation->power,
                'matchedPlayerId' => $matchedPlayerId,
                'identityState' => $identityState,
                'reasons' => array_values(array_unique($reasons)),
                'handoff' => '/alliance',
            ];
        }

        if ($completeRoster) {
            foreach ($memberships as $membership) {
                $playerId = (string) $membership->player_id;
                if (isset($matchedPlayers[$playerId])) {
                    continue;
                }
                $ref = $playerRefs[$playerId] ?? null;
                $items[] = [
                    'observationId' => null,
                    'observedName' => $ref->currentName ?? 'Unknown Governor',
                    'gamePlayerId' => $ref?->gamePlayerId,
                    'observedRank' => null,
                    'power' => null,
                    'matchedPlayerId' => $playerId,
                    'identityState' => 'membership_only',
                    'reasons' => ['membership_without_observation', 'observed_missing'],
                    'handoff' => '/alliance',
                ];
            }
        }

        $needsReview = count(array_filter($items, static fn (array $item): bool => $item['reasons'] !== ['matches_membership']));

        return [
            'batch' => [
                'id' => (string) $batch->id,
                'capturedAt' => $batch->captured_at->toIso8601String(),
                'evidenceId' => (string) $batch->source_evidence_id,
                'reviewId' => (string) $batch->source_review_id,
                'completeRoster' => $completeRoster,
            ],
            'items' => $items,
            'summary' => [
                'needsReview' => $needsReview,
                'matched' => count($items) - $needsReview,
            ],
        ];
    }
}
