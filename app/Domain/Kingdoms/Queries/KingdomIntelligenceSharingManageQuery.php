<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareTargetState;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use Illuminate\Support\Facades\DB;

final class KingdomIntelligenceSharingManageQuery
{
    public const AGREEMENT_LIMIT = 100;

    public const TRACKING_LIMIT = 250;

    /**
     * @return array{
     *   outbound: list<array<string, mixed>>,
     *   inbound: list<array<string, mixed>>,
     *   trackableTargets: list<array{id: string, name: string, tag: string|null}>
     * }
     */
    public function forAlliance(Alliance $alliance): array
    {
        $outbound = DB::table('kingdom_intelligence_shares as shares')
            ->leftJoin('alliances as recipient', 'recipient.id', '=', 'shares.recipient_alliance_id')
            ->where('shares.source_alliance_id', $alliance->id)
            ->select([
                'shares.id',
                'shares.state',
                'shares.kingdom_id',
                'shares.recipient_alliance_id',
                'recipient.name as recipient_name',
                'shares.invitation_expires_at',
                'shares.invitation_used_at',
                'shares.accepted_at',
                'shares.declined_at',
                'shares.revoked_at',
                'shares.created_at',
            ])
            ->orderByDesc('shares.created_at')
            ->limit(self::AGREEMENT_LIMIT)
            ->get();

        $outboundIds = $outbound
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $targets = collect();
        if ($outboundIds !== []) {
            $targets = DB::table('kingdom_intelligence_share_targets as targets')
                ->join(
                    'tracked_kingdom_alliances as tracking',
                    'tracking.id',
                    '=',
                    'targets.tracked_kingdom_alliance_id',
                )
                ->join('kingdom_alliances as game_alliances', 'game_alliances.id', '=', 'tracking.kingdom_alliance_id')
                ->whereIn('targets.kingdom_intelligence_share_id', $outboundIds)
                ->select([
                    'targets.id',
                    'targets.kingdom_intelligence_share_id',
                    'targets.state',
                    'targets.shared_at',
                    'targets.removed_at',
                    'tracking.id as tracking_id',
                    'game_alliances.current_name',
                    'game_alliances.current_tag',
                ])
                ->orderBy('game_alliances.current_name')
                ->orderBy('targets.id')
                ->get()
                ->groupBy('kingdom_intelligence_share_id');
        }

        $outboundRows = [];
        foreach ($outbound as $share) {
            $shareTargets = $targets->get((string) $share->id, collect());
            $outboundRows[] = [
                'id' => (string) $share->id,
                'state' => (string) $share->state,
                'kingdomId' => (string) $share->kingdom_id,
                'recipientAlliance' => $share->recipient_alliance_id === null ? null : [
                    'id' => (string) $share->recipient_alliance_id,
                    'name' => (string) $share->recipient_name,
                ],
                'invitationExpiresAt' => (string) $share->invitation_expires_at,
                'invitationUsedAt' => $share->invitation_used_at === null ? null : (string) $share->invitation_used_at,
                'acceptedAt' => $share->accepted_at === null ? null : (string) $share->accepted_at,
                'declinedAt' => $share->declined_at === null ? null : (string) $share->declined_at,
                'revokedAt' => $share->revoked_at === null ? null : (string) $share->revoked_at,
                'targets' => $shareTargets->map(static fn (object $target): array => [
                    'id' => (string) $target->id,
                    'trackingId' => (string) $target->tracking_id,
                    'state' => (string) $target->state,
                    'name' => (string) $target->current_name,
                    'tag' => $target->current_tag === null ? null : (string) $target->current_tag,
                    'sharedAt' => (string) $target->shared_at,
                    'removedAt' => $target->removed_at === null ? null : (string) $target->removed_at,
                ])->values()->all(),
            ];
        }

        $inbound = DB::table('kingdom_intelligence_shares as shares')
            ->join('alliances as source', 'source.id', '=', 'shares.source_alliance_id')
            ->where('shares.recipient_alliance_id', $alliance->id)
            ->select([
                'shares.id',
                'shares.state',
                'shares.kingdom_id',
                'shares.source_alliance_id',
                'source.name as source_name',
                'shares.accepted_at',
                'shares.declined_at',
                'shares.revoked_at',
                'shares.created_at',
            ])
            ->orderByDesc('shares.created_at')
            ->limit(self::AGREEMENT_LIMIT)
            ->get()
            ->map(static fn (object $share): array => [
                'id' => (string) $share->id,
                'state' => (string) $share->state,
                'kingdomId' => (string) $share->kingdom_id,
                'sourceAlliance' => [
                    'id' => (string) $share->source_alliance_id,
                    'name' => (string) $share->source_name,
                ],
                'acceptedAt' => $share->accepted_at === null ? null : (string) $share->accepted_at,
                'declinedAt' => $share->declined_at === null ? null : (string) $share->declined_at,
                'revokedAt' => $share->revoked_at === null ? null : (string) $share->revoked_at,
            ])
            ->values()
            ->all();

        $trackableTargets = DB::table('tracked_kingdom_alliances as tracking')
            ->join('kingdom_alliances as game_alliances', 'game_alliances.id', '=', 'tracking.kingdom_alliance_id')
            ->where('tracking.alliance_id', $alliance->id)
            ->where('tracking.state', TrackedKingdomAllianceState::Active->value)
            ->when(
                $alliance->kingdom_id !== null,
                static fn ($query) => $query->where('tracking.kingdom_id', $alliance->kingdom_id),
                static fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->select([
                'tracking.id',
                'game_alliances.current_name',
                'game_alliances.current_tag',
            ])
            ->orderBy('game_alliances.current_name')
            ->orderBy('tracking.id')
            ->limit(self::TRACKING_LIMIT)
            ->get()
            ->map(static fn (object $tracking): array => [
                'id' => (string) $tracking->id,
                'name' => (string) $tracking->current_name,
                'tag' => $tracking->current_tag === null ? null : (string) $tracking->current_tag,
            ])
            ->all();

        return [
            'outbound' => $outboundRows,
            'inbound' => $inbound,
            'trackableTargets' => $trackableTargets,
        ];
    }
}
