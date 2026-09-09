<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCapacity;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCondition;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\Models\Player;
use Carbon\CarbonImmutable;

final class KingdomTransferCurrentRulesVisualFixture
{
    public static function augment(): void
    {
        $now = CarbonImmutable::parse('2026-08-23 16:00:00', 'UTC');
        $currentThrough = CarbonImmutable::parse('2099-08-24 16:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);

        $actor = Player::query()
            ->where('game_player_id', 'TRANSFER-VISUAL-A')
            ->firstOrFail();
        $plan = TransferPlan::query()
            ->where('label', 'Northern Crown transfer board')
            ->firstOrFail();

        $allianceId = (string) $plan->getAttribute('alliance_id');
        $windowId = (string) $plan->getAttribute('transfer_window_id');
        $actorPlayerId = (string) $actor->id;
        $validUntil = $currentThrough->toIso8601String();

        app(RecordTransferKingdomCondition::class)->handle(
            allianceId: $allianceId,
            actorPlayerId: $actorPlayerId,
            windowId: $windowId,
            kingdomNumber: 1524,
            powerCap: 125_000_000,
            classification: TransferKingdomClassification::Ordinary,
            sourceType: TransferSourceType::InGame,
            sourceReference: 'KingShot Kingdom 1524 transfer screen',
            observedAt: $now->subMinutes(29)->toIso8601String(),
            isCorrection: true,
            heroGeneration: 7,
            truegoldLevel: 5,
            characterAgeThresholdDays: 120,
        );

        app(RecordTransferKingdomCapacity::class)->handle(
            allianceId: $allianceId,
            actorPlayerId: $actorPlayerId,
            windowId: $windowId,
            kingdomNumber: 1524,
            ordinaryInvitesUsed: 4,
            transferOpensUsed: 3,
            specialInvitesAvailable: 2,
            sourceType: TransferSourceType::InGame,
            sourceReference: 'KingShot Kingdom 1524 transfer capacity screen',
            observedAt: $now->subMinutes(25)->toIso8601String(),
        );

        /** @var list<TransferParticipant> $participants */
        $participants = TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->get()
            ->all();

        foreach ($participants as $participant) {
            self::record(
                $allianceId,
                $actorPlayerId,
                $plan,
                $participant,
                TransferObservationKind::HeroGeneration,
                7,
                'KingShot Governor hero generation screen',
                $now->subMinutes(24),
                $validUntil,
            );
            self::record(
                $allianceId,
                $actorPlayerId,
                $plan,
                $participant,
                TransferObservationKind::TruegoldLevel,
                5,
                'KingShot Governor Truegold screen',
                $now->subMinutes(23),
                $validUntil,
            );
            self::record(
                $allianceId,
                $actorPlayerId,
                $plan,
                $participant,
                TransferObservationKind::CharacterAgeOverTargetDays,
                30,
                'KingShot transfer eligibility screen',
                $now->subMinutes(22),
                $validUntil,
            );
            self::record(
                $allianceId,
                $actorPlayerId,
                $plan,
                $participant,
                TransferObservationKind::TransferCooldownRemainingDays,
                0,
                'KingShot transfer cooldown screen',
                $now->subMinutes(21),
                $validUntil,
            );
            self::record(
                $allianceId,
                $actorPlayerId,
                $plan,
                $participant,
                TransferObservationKind::TargetExistingCharacterCount,
                1,
                'KingShot target Kingdom character list',
                $now->subMinutes(20),
                $validUntil,
            );
            self::record(
                $allianceId,
                $actorPlayerId,
                $plan,
                $participant,
                TransferObservationKind::ResourceProtectionVerified,
                true,
                'KingShot Storehouse and transfer pre-flight',
                $now->subMinutes(19),
                $validUntil,
            );
        }
    }

    private static function record(
        string $allianceId,
        string $actorPlayerId,
        TransferPlan $plan,
        TransferParticipant $participant,
        TransferObservationKind $kind,
        int|bool $value,
        string $sourceReference,
        CarbonImmutable $observedAt,
        string $validUntil,
    ): void {
        app(RecordTransferObservation::class)->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            $kind,
            $value,
            TransferSourceType::InGame,
            $sourceReference,
            $observedAt->toIso8601String(),
            $validUntil,
        );
    }
}
