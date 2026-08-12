<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Platform\Services\OutboxRecorder;

final readonly class InvalidateKingdomIntelligenceSharesForAllianceDrift
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $changedAlliance, User $actor): int
    {
        $changed = 0;

        $sourceShares = KingdomIntelligenceShare::query()
            ->where('source_alliance_id', $changedAlliance->id)
            ->whereIn('state', [
                KingdomIntelligenceShareState::Pending->value,
                KingdomIntelligenceShareState::Active->value,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($sourceShares as $share) {
            $occurredAt = now();
            $share->forceFill([
                'state' => KingdomIntelligenceShareState::Revoked,
                'revoked_by_user_id' => $actor->id,
                'revoked_at' => $occurredAt,
            ])->save();

            $metadata = $this->metadata($share, 'source_kingdom_changed');
            $this->record($changedAlliance, $actor, $share, $metadata, $occurredAt);
            $this->recordCounterpart($share, $changedAlliance, $share->recipient_alliance_id, $metadata, $occurredAt);
            $changed++;
        }

        $recipientShares = KingdomIntelligenceShare::query()
            ->where('recipient_alliance_id', $changedAlliance->id)
            ->where('state', KingdomIntelligenceShareState::Active->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($recipientShares as $share) {
            $occurredAt = now();
            $share->forceFill([
                'state' => KingdomIntelligenceShareState::Declined,
                'declined_by_user_id' => $actor->id,
                'declined_at' => $occurredAt,
            ])->save();

            $metadata = $this->metadata($share, 'recipient_kingdom_changed');
            $this->record($changedAlliance, $actor, $share, $metadata, $occurredAt);
            $this->recordCounterpart($share, $changedAlliance, $share->source_alliance_id, $metadata, $occurredAt);
            $changed++;
        }

        return $changed;
    }

    /** @return array<string, mixed> */
    private function metadata(KingdomIntelligenceShare $share, string $reason): array
    {
        return [
            'share_id' => (string) $share->id,
            'source_alliance_id' => (string) $share->source_alliance_id,
            'recipient_alliance_id' => $share->recipient_alliance_id === null
                ? null
                : (string) $share->recipient_alliance_id,
            'kingdom_id' => (string) $share->kingdom_id,
            'state' => $share->state->value,
            'reason' => $reason,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function record(
        Alliance $alliance,
        ?User $actor,
        KingdomIntelligenceShare $share,
        array $metadata,
        \DateTimeInterface $occurredAt,
    ): void {
        $event = 'kingdoms.shared_intelligence_context_invalidated';
        $this->audit->record($event, $actor, $share, $alliance, $metadata);
        $this->outbox->record(
            $event,
            (string) $alliance->id,
            $share,
            $metadata,
            $event.':'.$share->id.':'.$alliance->id.':'.$occurredAt->format('YmdHis.u'),
        );
    }

    /** @param array<string, mixed> $metadata */
    private function recordCounterpart(
        KingdomIntelligenceShare $share,
        Alliance $changedAlliance,
        ?string $counterpartAllianceId,
        array $metadata,
        \DateTimeInterface $occurredAt,
    ): void {
        if ($counterpartAllianceId === null || $counterpartAllianceId === (string) $changedAlliance->id) {
            return;
        }

        $counterpart = Alliance::query()->find($counterpartAllianceId);
        if ($counterpart instanceof Alliance) {
            $this->record($counterpart, null, $share, $metadata, $occurredAt);
        }
    }
}
