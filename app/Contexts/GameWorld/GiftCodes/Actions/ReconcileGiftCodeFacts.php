<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeFactResolver;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileGiftCodeFacts
{
    public function __construct(
        private GiftCodeFactResolver $resolver,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @return array<string,int> */
    public function handle(string $giftCodeId, ?AuditActor $actor = null): array
    {
        return DB::transaction(function () use ($giftCodeId, $actor): array {
            $giftCode = GiftCode::query()->whereKey($giftCodeId)->lockForUpdate()->firstOrFail();
            $revisions = [];

            foreach ($this->resolver->resolve($giftCode) as $decision) {
                $projection = GiftCodeFactProjection::query()
                    ->where('gift_code_id', $giftCodeId)
                    ->where('fact_type', $decision->factType)
                    ->lockForUpdate()
                    ->first();
                $same = $projection instanceof GiftCodeFactProjection
                    && $projection->qualified === $decision->qualified
                    && $projection->reason_code === $decision->reasonCode
                    && ($projection->value ?? null) === $decision->value
                    && ($projection->evidence_ids ?? []) === $decision->evidenceIds;
                if ($same) {
                    $revisions[$decision->factType] = $projection->revision;

                    continue;
                }

                $projection ??= new GiftCodeFactProjection([
                    'gift_code_id' => $giftCodeId,
                    'fact_type' => $decision->factType,
                    'revision' => 0,
                ]);
                $revision = $projection->revision + 1;
                $projection->forceFill([
                    'qualified' => $decision->qualified,
                    'reason_code' => $decision->reasonCode,
                    'value' => $decision->value,
                    'evidence_ids' => $decision->evidenceIds,
                    'revision' => $revision,
                    'derived_at' => now(),
                ])->save();

                $metadata = [
                    'version' => 1,
                    'gift_code_id' => $giftCodeId,
                    'fact_type' => $decision->factType,
                    'qualified' => $decision->qualified,
                    'reason_code' => $decision->reasonCode,
                    'revision' => $revision,
                    'evidence_ids' => $decision->evidenceIds,
                ];
                $this->audit->record('game_world.gift_code_fact_reconciled', $actor, $projection, null, $metadata);
                $this->outbox->record(
                    'gift_code.fact_changed',
                    null,
                    $projection,
                    $metadata,
                    'gift-code:'.$giftCodeId.':fact:'.$decision->factType.':revision:'.$revision,
                    'gift-code:'.$giftCodeId,
                );
                $revisions[$decision->factType] = $revision;
            }

            return $revisions;
        });
    }
}
