<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareTargetState;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AddKingdomIntelligenceShareTarget
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $sourceAlliance,
        User $actor,
        string $shareId,
        string $trackingId,
    ): KingdomIntelligenceShareTarget {
        if (! $this->authorization->allows($actor, $sourceAlliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($sourceAlliance, $actor, $shareId, $trackingId): KingdomIntelligenceShareTarget {
            $candidate = KingdomIntelligenceShare::query()
                ->whereKey($shareId)
                ->where('source_alliance_id', $sourceAlliance->id)
                ->where('state', KingdomIntelligenceShareState::Active->value)
                ->firstOrFail();

            if ($candidate->recipient_alliance_id === null) {
                throw ValidationException::withMessages([
                    'sharing' => 'Only an active sharing agreement with a recipient can receive shared targets.',
                ]);
            }

            $allianceIds = [$candidate->source_alliance_id, $candidate->recipient_alliance_id];
            sort($allianceIds, SORT_STRING);
            $alliances = Alliance::query()
                ->whereIn('id', $allianceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (Alliance $alliance): string => (string) $alliance->id);

            /** @var Alliance|null $source */
            $source = $alliances->get($candidate->source_alliance_id);
            /** @var Alliance|null $recipient */
            $recipient = $alliances->get($candidate->recipient_alliance_id);

            if (! $source instanceof Alliance || ! $recipient instanceof Alliance) {
                throw ValidationException::withMessages([
                    'sharing' => 'Both sharing alliances must still exist before a target can be shared.',
                ]);
            }

            $share = KingdomIntelligenceShare::query()
                ->whereKey($candidate->id)
                ->where('source_alliance_id', $source->id)
                ->where('recipient_alliance_id', $recipient->id)
                ->where('state', KingdomIntelligenceShareState::Active->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ($source->status !== AllianceStatus::Active
                || $recipient->status !== AllianceStatus::Active
                || $source->kingdom_id === null
                || $source->kingdom_id !== $share->kingdom_id
                || $recipient->kingdom_id !== $share->kingdom_id) {
                throw ValidationException::withMessages([
                    'sharing' => 'Both alliances must be active in the captured Kingdom before a target can be shared.',
                ]);
            }

            $tracking = TrackedKingdomAlliance::query()
                ->whereKey($trackingId)
                ->where('alliance_id', $source->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tracking->state !== TrackedKingdomAllianceState::Active
                || $tracking->kingdom_id !== $share->kingdom_id) {
                throw ValidationException::withMessages([
                    'tracking' => 'Only an actively tracked game alliance in the captured Kingdom can be shared.',
                ]);
            }

            $target = KingdomIntelligenceShareTarget::query()
                ->where('kingdom_intelligence_share_id', $share->id)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->lockForUpdate()
                ->first();

            if ($target instanceof KingdomIntelligenceShareTarget
                && $target->state === KingdomIntelligenceShareTargetState::Active) {
                return $target;
            }

            $sharedAt = now();
            if ($target instanceof KingdomIntelligenceShareTarget) {
                $target->forceFill([
                    'state' => KingdomIntelligenceShareTargetState::Active,
                    'shared_by_user_id' => $actor->id,
                    'removed_by_user_id' => null,
                    'shared_at' => $sharedAt,
                    'removed_at' => null,
                ])->save();
            } else {
                $target = KingdomIntelligenceShareTarget::query()->create([
                    'kingdom_intelligence_share_id' => $share->id,
                    'tracked_kingdom_alliance_id' => $tracking->id,
                    'state' => KingdomIntelligenceShareTargetState::Active,
                    'shared_by_user_id' => $actor->id,
                    'shared_at' => $sharedAt,
                ]);
            }

            $metadata = $this->metadata($share, $target);
            $this->recordForAlliance($source, $actor, $target, $metadata, $sharedAt);
            $this->recordForAlliance($recipient, null, $target, $metadata, $sharedAt);

            return $target->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function metadata(
        KingdomIntelligenceShare $share,
        KingdomIntelligenceShareTarget $target,
    ): array {
        return [
            'share_target_id' => (string) $target->id,
            'share_id' => (string) $share->id,
            'source_alliance_id' => (string) $share->source_alliance_id,
            'recipient_alliance_id' => (string) $share->recipient_alliance_id,
            'kingdom_id' => (string) $share->kingdom_id,
            'state' => $target->state->value,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function recordForAlliance(
        Alliance $alliance,
        ?User $actor,
        KingdomIntelligenceShareTarget $target,
        array $metadata,
        \DateTimeInterface $occurredAt,
    ): void {
        $event = 'kingdoms.shared_intelligence_target_shared';
        $this->audit->record($event, $actor, $target, $alliance, $metadata);
        $this->outbox->record(
            $event,
            (string) $alliance->id,
            $target,
            $metadata,
            $event.':'.$target->id.':'.$alliance->id.':'.$occurredAt->format('YmdHis.u'),
        );
    }
}
