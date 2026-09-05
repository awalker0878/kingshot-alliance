<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\GiftCodes\Actions\CreateGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\UpdateGiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeRedemptionWorkspaceV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game_world.gift_codes.redemption_workspace', true);
        config()->set('game_world.gift_codes.max_session_codes', 100);
        config()->set('game_world.gift_codes.max_session_governors', 50);
        config()->set('game_world.gift_codes.max_session_items', 500);
    }

    public function test_personal_state_is_idempotent_and_never_mutates_catalogue_truth(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->validGiftCode('PERSONAL-STATE');
        $statusRevision = $giftCode->status_revision;
        $expiresRevision = $giftCode->expires_revision;

        app(UpdateGiftCodeAccountState::class)->handle(
            $actor,
            (string) $giftCode->id,
            GiftCodeAccountStateStatus::Pinned,
        );
        app(UpdateGiftCodeAccountState::class)->handle(
            $actor,
            (string) $giftCode->id,
            GiftCodeAccountStateStatus::Pinned,
        );

        self::assertSame(1, GiftCodeAccountState::query()
            ->where('user_id', $account->userId)
            ->where('gift_code_id', $giftCode->id)
            ->count());
        self::assertSame(GiftCodeAccountStateStatus::Pinned, GiftCodeAccountState::query()->firstOrFail()->state);

        $giftCode->refresh();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame($statusRevision, $giftCode->status_revision);
        self::assertSame($expiresRevision, $giftCode->expires_revision);
    }

    public function test_selected_session_deduplicates_selectors_and_honors_qualified_applicability(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $included = $scenarios->player($account->userId, 1501, 'GCW-1501');
        $excluded = $scenarios->player($account->userId, 1502, 'GCW-1502');
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->validGiftCode('QUALIFIED-APPLICABILITY');

        GiftCodeFactProjection::query()->create([
            'gift_code_id' => $giftCode->id,
            'fact_type' => 'applicability',
            'qualified' => true,
            'reason_code' => 'qualified_applicability_evidence',
            'value' => ['kingdoms' => [1501]],
            'evidence_ids' => [],
            'revision' => 1,
            'derived_at' => now(),
        ]);

        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $giftCode->id, (string) $giftCode->id],
            [$included->playerId, $included->playerId, $excluded->playerId],
        );
        $session = GiftCodeRedemptionSession::query()->with('items')->findOrFail($reference->sessionId);

        self::assertSame(2, $session->items->count());
        self::assertSame(1, $session->items->where('state', GiftCodeRedemptionSessionItemState::Ready)->count());
        $unavailable = $session->items->firstWhere('state', GiftCodeRedemptionSessionItemState::Unavailable);
        self::assertNotNull($unavailable);
        self::assertSame('qualified_applicability_excludes_governor', $unavailable->unavailable_reason);
    }

    public function test_session_creation_rejects_a_foreign_governor_selector(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $owned = $scenarios->player($account->userId, 1601, 'GCW-1601');
        $foreign = $scenarios->player($other->userId, 1602, 'GCW-1602');
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->validGiftCode('FOREIGN-GOVERNOR');

        try {
            app(CreateGiftCodeRedemptionSession::class)->handle(
                $actor,
                GiftCodeRedemptionSessionMode::Selected,
                [(string) $giftCode->id],
                [$owned->playerId, $foreign->playerId],
            );
            self::fail('Foreign Governor selectors must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('player_ids', $exception->errors());
        }
    }

    public function test_reconciliation_makes_pending_session_items_unavailable_after_trust_changes(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 1701, 'GCW-1701');
        $actor = User::query()->findOrFail($account->userId);
        $giftCode = $this->validGiftCode('TRUST-CHANGE');

        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $giftCode->id],
            [$player->playerId],
        );
        $session = GiftCodeRedemptionSession::query()->with('items')->findOrFail($reference->sessionId);
        self::assertSame(GiftCodeRedemptionSessionItemState::Ready, $session->items->firstOrFail()->state);

        $giftCode->forceFill([
            'status' => GiftCodeStatus::Disputed,
            'status_revision' => $giftCode->status_revision + 1,
            'status_reason_code' => 'credible_evidence_conflict',
            'status_changed_at' => now(),
            'status_derived_at' => now(),
        ])->save();

        app(ReconcileGiftCodeRedemptionSession::class)->handle($actor, (string) $session->id);
        $reconciled = GiftCodeRedemptionSession::query()->with('items')->findOrFail($session->id);
        $item = $reconciled->items->firstOrFail();
        self::assertSame(GiftCodeRedemptionSessionItemState::Unavailable, $item->state);
        self::assertSame('trust_not_valid', $item->unavailable_reason);
        self::assertSame($giftCode->status_revision, $item->status_revision_snapshot);

        self::assertSame(1, GiftCodeRedemptionSessionItem::query()
            ->where('session_id', $session->id)
            ->where('state', GiftCodeRedemptionSessionItemState::Unavailable->value)
            ->count());
    }

    private function validGiftCode(string $code): GiftCode
    {
        return GiftCode::query()->create([
            'code' => $code,
            'normalized_code' => mb_strtoupper($code),
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);
    }
}
