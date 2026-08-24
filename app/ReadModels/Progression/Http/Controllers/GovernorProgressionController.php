<?php

declare(strict_types=1);

namespace App\ReadModels\Progression\Http\Controllers;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GovernorProgressionController extends Controller
{
    public function __invoke(
        Request $request,
        PlayerContext $context,
        ProgressionDatasetQuery $datasets,
        AllianceIntelligenceAuthorization $intelligence,
    ): Response {
        $player = $context->player();
        $dataset = $datasets->latest();

        $membership = AllianceMembership::query()
            ->where('player_id', $player->playerId)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        $allianceId = $membership?->getAttribute('alliance_id');
        $allianceId = is_string($allianceId) ? $allianceId : null;
        $canViewObservations = $allianceId !== null
            && $intelligence->allows($player->playerId, $allianceId, IntelligencePermission::View);
        $canManageObservations = $allianceId !== null
            && $intelligence->allows($player->playerId, $allianceId, IntelligencePermission::KingdomManage);

        $entry = $canViewObservations
            ? AllianceRosterEntry::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $player->playerId)
                ->orderByDesc('created_at')
                ->first()
            : null;

        $snapshots = $entry instanceof AllianceRosterEntry
            ? PlayerSnapshot::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $player->playerId)
                ->orderByDesc('captured_at')
                ->limit(50)
                ->get()
            : collect();

        $formations = PlayerFormation::query()
            ->where('player_id', $player->playerId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $user = $request->user();

        return Inertia::render('Kingdom/Progression/Governor', [
            'user' => ['name' => (string) $user?->name, 'email' => (string) $user?->email],
            'governor' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomNumber' => $player->kingdomNumber,
                'allianceId' => $allianceId,
                'rosterEntryId' => $entry instanceof AllianceRosterEntry ? (string) $entry->id : null,
            ],
            'dataset' => [
                'id' => $dataset->id,
                'version' => $dataset->datasetVersion,
                'checksum' => $dataset->checksum,
                'heroes' => array_map(static fn (array $hero): array => [
                    'id' => (string) ($hero['id'] ?? ''),
                    'name' => (string) ($hero['name'] ?? ''),
                    'generation' => (int) ($hero['generation'] ?? 0),
                    'rarity' => (string) ($hero['rarity'] ?? ''),
                    'troopClass' => (string) ($hero['troop_class'] ?? ''),
                ], $dataset->heroes),
            ],
            'observationAccess' => [
                'canView' => $canViewObservations,
                'canManage' => $canManageObservations,
            ],
            'observations' => $snapshots->map(static fn (PlayerSnapshot $snapshot): array => [
                'id' => (string) $snapshot->id,
                'capturedAt' => $snapshot->captured_at->toIso8601String(),
                'source' => (string) $snapshot->source,
                'power' => (string) $snapshot->power,
                'progressionLevel' => $snapshot->progression_level,
                'datasetId' => $snapshot->progression_dataset_id,
                'datasetChecksum' => $snapshot->progression_dataset_checksum,
                'heroObservations' => is_array($snapshot->hero_observations) ? $snapshot->hero_observations : [],
            ])->values()->all(),
            'loadouts' => $formations->map(static fn (PlayerFormation $formation): array => [
                'id' => (string) $formation->id,
                'name' => (string) $formation->name,
                'infantryPercent' => (int) $formation->infantry_percent,
                'cavalryPercent' => (int) $formation->cavalry_percent,
                'archerPercent' => (int) $formation->archer_percent,
                'heroes' => is_array($formation->heroes) ? $formation->heroes : [],
                'notes' => $formation->notes,
                'isDefault' => (bool) $formation->is_default,
                'datasetId' => $formation->progression_dataset_id,
                'datasetChecksum' => $formation->progression_dataset_checksum,
            ])->values()->all(),
        ]);
    }
}
