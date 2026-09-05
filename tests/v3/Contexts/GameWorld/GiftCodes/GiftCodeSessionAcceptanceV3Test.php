<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\GiftCodes\Actions\AbandonGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\CreateGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeSessionAcceptanceV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game_world.gift_codes.redemption_workspace', true);
    }

    /** @return iterable<string, array{int,int}> */
    public static function sessionShapes(): iterable
    {
        yield 'one code one Governor' => [1, 1];
        yield 'one code many Governors' => [1, 3];
        yield 'many codes one Governor' => [3, 1];
        yield 'many codes many Governors' => [3, 3];
    }

    #[DataProvider('sessionShapes')]
    public function test_selected_session_supports_every_code_governor_cardinality(int $codeCount, int $governorCount): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $actor = User::query()->findOrFail($account->userId);
        $playerIds = [];
        for ($index = 0; $index < $governorCount; $index++) {
            $playerIds[] = $scenarios->player(
                $account->userId,
                2400 + $index,
                sprintf('GCW-SHAPE-GOV-%d-%d-%d', $codeCount, $governorCount, $index),
            )->playerId;
        }
        $giftCodeIds = [];
        for ($index = 0; $index < $codeCount; $index++) {
            $giftCodeIds[] = (string) $this->code(
                sprintf('GCW-SHAPE-%d-%d-%d', $codeCount, $governorCount, $index),
                GiftCodeStatus::Valid,
            )->id;
        }

        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            $giftCodeIds,
            $playerIds,
        );
        $expected = $codeCount * $governorCount;
        $session = GiftCodeRedemptionSession::query()->with('items')->findOrFail($reference->sessionId);

        self::assertSame($expected, $reference->totalItems);
        self::assertSame($expected, $session->items->count());
        self::assertSame($expected, $session->items->where('state', GiftCodeRedemptionSessionItemState::Ready)->count());
        self::assertSame(
            $expected,
            $session->items->map(static fn (GiftCodeRedemptionSessionItem $item): string => $item->gift_code_id.'|'.$item->player_id)->unique()->count(),
        );
    }

    public function test_selected_session_marks_non_valid_catalogue_states_unavailable(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 2450, 'GCW-NONVALID-GOV');
        $actor = User::query()->findOrFail($account->userId);
        $ids = [];
        foreach ([
            GiftCodeStatus::Invalid,
            GiftCodeStatus::Expired,
            GiftCodeStatus::Disputed,
            GiftCodeStatus::Quarantined,
        ] as $index => $status) {
            $ids[] = (string) $this->code('GCW-NONVALID-'.$index, $status)->id;
        }

        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            $ids,
            [$player->playerId],
        );
        $items = GiftCodeRedemptionSessionItem::query()
            ->where('session_id', $reference->sessionId)
            ->orderBy('sequence')
            ->get();

        self::assertCount(4, $items);
        foreach ($items as $item) {
            self::assertSame(GiftCodeRedemptionSessionItemState::Unavailable, $item->state);
            self::assertSame('trust_not_valid', $item->unavailable_reason);
        }
    }

    public function test_missing_game_player_id_is_unavailable_with_stable_reason(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 2460, 'GCW-MISSING-ID');
        Player::query()->whereKey($player->playerId)->update(['game_player_id' => null]);
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->code('GCW-MISSING-ID-CODE', GiftCodeStatus::Valid);

        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $giftCode->id],
            [$player->playerId],
        );
        $item = GiftCodeRedemptionSessionItem::query()->where('session_id', $reference->sessionId)->firstOrFail();

        self::assertSame(GiftCodeRedemptionSessionItemState::Unavailable, $item->state);
        self::assertSame('missing_game_player_id', $item->unavailable_reason);
    }

    public function test_reconciliation_rechecks_current_governor_ownership(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $player = $scenarios->player($account->userId, 2470, 'GCW-OWNERSHIP');
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->code('GCW-OWNERSHIP-CODE', GiftCodeStatus::Valid);
        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $giftCode->id],
            [$player->playerId],
        );

        Player::query()->whereKey($player->playerId)->update(['user_id' => $other->userId]);
        app(ReconcileGiftCodeRedemptionSession::class)->handle($actor, $reference->sessionId);
        $item = GiftCodeRedemptionSessionItem::query()->where('session_id', $reference->sessionId)->firstOrFail();

        self::assertSame(GiftCodeRedemptionSessionItemState::Unavailable, $item->state);
        self::assertSame('governor_unavailable', $item->unavailable_reason);
    }

    public function test_abandonment_is_explicit_and_persistent(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 2480, 'GCW-ABANDON');
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->code('GCW-ABANDON-CODE', GiftCodeStatus::Valid);
        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $giftCode->id],
            [$player->playerId],
        );

        app(AbandonGiftCodeRedemptionSession::class)->handle($actor, $reference->sessionId);
        $session = GiftCodeRedemptionSession::query()->findOrFail($reference->sessionId);

        self::assertSame(GiftCodeRedemptionSessionStatus::Abandoned, $session->status);
        self::assertNotNull($session->abandoned_at);
    }

    private function code(string $code, GiftCodeStatus $status): GiftCode
    {
        return GiftCode::query()->create([
            'code' => $code,
            'normalized_code' => mb_strtoupper($code),
            'status' => $status,
            'status_revision' => 1,
            'status_reason_code' => 'acceptance_fixture',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);
    }
}
