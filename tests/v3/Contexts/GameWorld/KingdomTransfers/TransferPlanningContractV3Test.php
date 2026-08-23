<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferPlanningContractV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_window_phase_boundaries_must_be_strictly_increasing(): void
    {
        $now = CarbonImmutable::parse('2026-08-23T16:00:00Z');
        CarbonImmutable::setTestNow($now);

        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $actor = $factory->player($account->userId, 7381, 'TRANSFER-CONTRACT-7381');
        $alliance = $factory->alliance($actor);

        try {
            app(SaveTransferWindow::class)->handle(
                $alliance->allianceId,
                $actor->playerId,
                [
                    'label' => 'Invalid transfer window',
                    'pre_transfer_starts_at' => $now->addDay()->toIso8601String(),
                    'invitational_starts_at' => $now->addDay()->toIso8601String(),
                    'transfer_opens_at' => $now->addDays(2)->toIso8601String(),
                    'ends_at' => $now->addDays(3)->toIso8601String(),
                    'source_type' => TransferSourceType::OfficialPublication,
                    'source_reference' => 'Century Games Kingdom Transfer notice',
                    'observed_at' => $now->toIso8601String(),
                ],
            );
            self::fail('Equal phase boundaries must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('window', $exception->errors());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_current_community_observation_remains_non_authoritative(): void
    {
        $now = CarbonImmutable::parse('2026-08-23T16:00:00Z');
        $observation = new TransferObservation([
            'kind' => TransferObservationKind::GovernorPower,
            'numeric_value' => 118_000_000,
            'source_type' => TransferSourceType::Community,
            'source_reference' => 'Community transfer guide',
            'observed_at' => $now->subMinutes(5),
            'valid_until' => $now->addHour(),
        ]);

        $selected = app(TransferObservationSelector::class)->select(
            collect([$observation]),
            TransferObservationKind::GovernorPower,
            null,
            $now,
        );

        self::assertSame(TransferRequirementState::Unknown, $selected->state);
        self::assertSame(TransferSourceType::Community, $selected->sourceType);
        self::assertSame(118_000_000, $selected->value);
    }

    public function test_active_member_can_view_transfer_planning_without_manage_authority(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $actor = $factory->player($account->userId, 7382, 'TRANSFER-CONTRACT-7382');
        $alliance = $factory->alliance($actor);

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->firstOrFail();
        $membership->forceFill(['rank' => AllianceRank::R1])->save();

        $authorization = app(TransferAuthorization::class);

        self::assertTrue($authorization->allows(
            $actor->playerId,
            $alliance->allianceId,
            TransferPermission::View,
        ));
        self::assertFalse($authorization->allows(
            $actor->playerId,
            $alliance->allianceId,
            TransferPermission::Manage,
        ));
    }
}
