<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\GiftCodes\Actions\CreateGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueDueGiftCodeReminders;
use App\Contexts\GameWorld\GiftCodes\Actions\RebuildGiftCodeContributorProjections;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\UpdateGiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeContributorProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeWorkspaceQuery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSignalService;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRewardPresenter;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeWorkspaceV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_personal_state_is_account_owned_and_never_mutates_catalogue_truth(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $scenarios->player($account->userId, 2101, 'GCW-PERSONAL');
        $actor = User::query()->findOrFail($account->userId);
        $code = $this->validCode('GCW-PERSONAL-CODE');
        $revision = $code->status_revision;

        app(UpdateGiftCodeAccountState::class)->handle(
            $actor,
            (string) $code->id,
            GiftCodeAccountStateStatus::Dismissed,
            remindAt: now()->addDay()->toImmutable(),
        );
        $state = GiftCodeAccountState::query()
            ->where('user_id', $account->userId)
            ->where('gift_code_id', $code->id)
            ->firstOrFail();

        self::assertSame(GiftCodeAccountStateStatus::Dismissed, $state->state);
        self::assertNotNull($state->remind_at);
        $code->refresh();
        self::assertSame(GiftCodeStatus::Valid, $code->status);
        self::assertSame($revision, $code->status_revision);
        self::assertSame(1, GiftCodeAccountState::query()->where('user_id', $account->userId)->count());
    }

    public function test_multi_code_multi_governor_session_excludes_success_and_persists_progress(): void
    {
        config()->set('game_world.gift_codes.redemption_workspace', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $first = $scenarios->player($account->userId, 2111, 'GCW-MULTI-A');
        $second = $scenarios->player($account->userId, 2112, 'GCW-MULTI-B');
        $actor = User::query()->findOrFail($account->userId);
        $firstCode = $this->validCode('GCW-MULTI-ONE');
        $secondCode = $this->validCode('GCW-MULTI-TWO');
        $this->redemption($firstCode, $first, GiftCodeRedemptionStatus::Redeemed);

        $reference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::AllActionable,
            playerIds: [$first->playerId, $second->playerId],
        );
        $session = GiftCodeRedemptionSession::query()->with('items')->findOrFail($reference->sessionId);

        self::assertSame(GiftCodeRedemptionSessionStatus::Active, $session->status);
        self::assertSame(3, $session->total_items);
        self::assertSame(3, GiftCodeRedemptionSessionItem::query()->where('session_id', $session->id)->count());
        self::assertFalse(GiftCodeRedemptionSessionItem::query()
            ->where('session_id', $session->id)
            ->where('gift_code_id', $firstCode->id)
            ->where('player_id', $first->playerId)
            ->exists());
        self::assertSame(2, GiftCodeRedemptionSessionItem::query()
            ->where('session_id', $session->id)
            ->where('gift_code_id', $secondCode->id)
            ->count());
    }

    public function test_retry_wait_promotes_when_due_and_trust_change_invalidates_stale_session(): void
    {
        config()->set('game_world.gift_codes.redemption_workspace', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 2121, 'GCW-RETRY');
        $actor = User::query()->findOrFail($account->userId);
        $retryCode = $this->validCode('GCW-RETRY-CODE');
        $retry = $this->redemption($retryCode, $player, GiftCodeRedemptionStatus::RateLimited);
        $retry->next_attempt_at = now()->addHour();
        $retry->save();

        $retryReference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $retryCode->id],
            [$player->playerId],
        );
        $retrySession = GiftCodeRedemptionSession::query()->with('items')->findOrFail($retryReference->sessionId);
        self::assertSame(
            GiftCodeRedemptionSessionItemState::RetryWait,
            $retrySession->items->firstOrFail()->state,
        );

        $retry->next_attempt_at = now()->subMinute();
        $retry->save();
        app(ReconcileGiftCodeRedemptionSession::class)->handle($actor, (string) $retrySession->id);
        $reconciled = GiftCodeRedemptionSession::query()->with('items')->findOrFail($retrySession->id);
        self::assertSame(GiftCodeRedemptionSessionItemState::Ready, $reconciled->items->firstOrFail()->state);

        $trustCode = $this->validCode('GCW-TRUST-CODE');
        $trustReference = app(CreateGiftCodeRedemptionSession::class)->handle(
            $actor,
            GiftCodeRedemptionSessionMode::Selected,
            [(string) $trustCode->id],
            [$player->playerId],
        );
        $trustSession = GiftCodeRedemptionSession::query()->with('items')->findOrFail($trustReference->sessionId);
        $trustCode->status = GiftCodeStatus::Disputed;
        $trustCode->status_revision++;
        $trustCode->status_reason_code = 'credible_conflict';
        $trustCode->status_changed_at = now();
        $trustCode->save();

        app(ReconcileGiftCodeRedemptionSession::class)->handle($actor, (string) $trustSession->id);
        $reconciled = GiftCodeRedemptionSession::query()->with('items')->findOrFail($trustSession->id);
        self::assertSame(GiftCodeRedemptionSessionStatus::Completed, $reconciled->status);
        self::assertSame(GiftCodeRedemptionSessionItemState::Unavailable, $reconciled->items->firstOrFail()->state);
        self::assertSame('trust_not_valid', $reconciled->items->firstOrFail()->unavailable_reason);
    }

    public function test_workspace_requires_success_for_every_owned_governor_before_completed(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $first = $scenarios->player($account->userId, 2131, 'GCW-VIEW-A');
        $second = $scenarios->player($account->userId, 2132, 'GCW-VIEW-B');
        $code = $this->validCode('GCW-VIEW-CODE');
        $this->redemption($code, $first, GiftCodeRedemptionStatus::Redeemed);
        $query = app(GiftCodeWorkspaceQuery::class);

        $ready = $query->pageForAccount($account->userId, [$first->playerId, $second->playerId], GiftCodeWorkspaceQuery::VIEW_READY);
        $completed = $query->pageForAccount($account->userId, [$first->playerId, $second->playerId], GiftCodeWorkspaceQuery::VIEW_COMPLETED);
        self::assertCount(1, $ready->items());
        self::assertCount(0, $completed->items());

        $this->redemption($code, $second, GiftCodeRedemptionStatus::AlreadyRedeemed);
        $completed = $query->pageForAccount($account->userId, [$first->playerId, $second->playerId], GiftCodeWorkspaceQuery::VIEW_COMPLETED);
        self::assertCount(1, $completed->items());
    }

    public function test_redemption_signal_is_hidden_until_distinct_account_privacy_threshold_passes(): void
    {
        config()->set('game_world.gift_codes.redemption_intelligence', true);
        config()->set('game_world.gift_codes.intelligence_min_samples', 2);
        config()->set('game_world.gift_codes.intelligence_min_accounts', 2);
        $scenarios = app(ScenarioFactory::class);
        $firstAccount = $scenarios->account();
        $secondAccount = $scenarios->account();
        $first = $scenarios->player($firstAccount->userId, 2141, 'GCW-SIGNAL-A');
        $second = $scenarios->player($secondAccount->userId, 2142, 'GCW-SIGNAL-B');
        $code = $this->validCode('GCW-SIGNAL-CODE');
        $this->redemption($code, $first, GiftCodeRedemptionStatus::Redeemed);

        $signals = app(GiftCodeRedemptionSignalService::class);
        self::assertNull($signals->forGiftCode((string) $code->id));

        $this->redemption($code, $second, GiftCodeRedemptionStatus::AlreadyRedeemed);
        $signal = $signals->forGiftCode((string) $code->id);
        self::assertNotNull($signal);
        self::assertSame(2, $signal['sampleCount']);
        self::assertSame(2, $signal['distinctAccounts']);
        self::assertSame(100.0, $signal['successRate']);
        self::assertArrayNotHasKey('playerIds', $signal);
        self::assertArrayNotHasKey('userIds', $signal);
    }

    public function test_structured_reward_presenter_never_promotes_unqualified_facts(): void
    {
        $code = $this->validCode('GCW-REWARD-CODE');
        GiftCodeFactProjection::query()->create([
            'gift_code_id' => $code->id,
            'fact_type' => 'reward',
            'qualified' => true,
            'reason_code' => 'qualified_evidence',
            'value' => ['items' => [
                ['type' => 'currency', 'key' => 'gold', 'quantity' => 500],
                ['type' => 'speedup', 'duration_seconds' => 3600, 'quantity' => 2],
            ]],
            'evidence_ids' => [],
            'revision' => 1,
            'derived_at' => now(),
        ]);
        $code->load('factProjections');

        $presented = app(GiftCodeRewardPresenter::class)->present($code);
        self::assertSame('qualified', $presented['state']);
        self::assertCount(2, $presented['items']);
        self::assertSame('gold', $presented['items'][0]['key']);

        GiftCodeFactProjection::query()->where('gift_code_id', $code->id)->update([
            'qualified' => false,
            'reason_code' => 'reward_conflict',
        ]);
        $code->unsetRelation('factProjections');
        $presented = app(GiftCodeRewardPresenter::class)->present($code);
        self::assertSame('reward_conflict', $presented['state']);
        self::assertSame([], $presented['items']);
    }

    public function test_signed_source_webhook_reuses_approved_ingestion_and_rejects_replay(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.source_webhook_ingestion', true);
        config()->set('game_world.gift_codes.source_webhook_secret', str_repeat('s', 40));
        $source = GiftCodeSourceRegistry::query()->create([
            'source_key' => 'workspace-webhook',
            'name' => 'Workspace webhook',
            'classification' => 'official',
            'canonical_domain' => 'source.example.test',
            'is_active' => true,
            'verification_method' => 'signed_webhook',
            'policy_revision' => 1,
            'provenance_policy' => ['auto_verify' => true],
            'ingestion_enabled' => true,
        ]);
        $payload = [
            'observations' => [[
                'code' => 'GCW-WEBHOOK',
                'assertion' => 'available',
                'source_url' => 'https://source.example.test/gift/gcw',
                'source_version' => 'publisher-v1',
                'content_fingerprint' => hash('sha256', 'gcw-webhook'),
            ]],
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac(
            'sha256',
            $source->id.'.'.$timestamp.'.'.$json,
            str_repeat('s', 40),
        );
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KINGSHOT_TIMESTAMP' => $timestamp,
            'HTTP_X_KINGSHOT_SIGNATURE' => 'sha256='.$signature,
        ];

        $response = $this->call(
            'POST',
            '/api/internal/gift-code-sources/'.$source->id.'/observations',
            [],
            [],
            [],
            $headers,
            $json,
        );
        $response->assertAccepted();
        self::assertSame(GiftCodeStatus::Valid, GiftCode::query()->where('normalized_code', 'GCW-WEBHOOK')->firstOrFail()->status);

        $this->call(
            'POST',
            '/api/internal/gift-code-sources/'.$source->id.'/observations',
            [],
            [],
            [],
            $headers,
            $json,
        )->assertUnprocessable();
    }

    public function test_due_reminder_queues_one_logical_communications_message(): void
    {
        config()->set('game_world.gift_codes.notification_fanout', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 2151, 'GCW-REMINDER');
        $code = $this->validCode('GCW-REMINDER-CODE');
        GiftCodeAccountState::query()->create([
            'gift_code_id' => $code->id,
            'user_id' => $account->userId,
            'state' => GiftCodeAccountStateStatus::Actionable,
            'remind_at' => now()->subMinute(),
            'last_action_at' => now()->subDay(),
        ]);
        app(SetNotificationPreference::class)->handle(
            $account->userId,
            null,
            'gift_code.reminder',
            DeliveryChannel::InApp,
            true,
        );

        self::assertSame(1, app(QueueDueGiftCodeReminders::class)->handle(10));
        $message = NotificationMessage::query()->where('notification_type', 'gift_code.reminder')->firstOrFail();
        self::assertSame($account->userId, $message->recipient_user_id);
        self::assertContains($player->playerId, $message->metadata['eligible_player_ids'] ?? []);
        self::assertNull(GiftCodeAccountState::query()->findOrFail(
            GiftCodeAccountState::query()->value('id'),
        )->remind_at);
    }

    public function test_contributor_projection_is_derived_but_rank_does_not_grant_alliance_coverage(): void
    {
        config()->set('game_world.gift_codes.contributor_reputation', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 2161, 'GCW-CONTRIB');
        $code = $this->validCode('GCW-CONTRIB-CODE');
        GiftCodeProvenance::query()->create([
            'gift_code_id' => $code->id,
            'submitted_by_player_id' => $player->playerId,
            'source_type' => GiftCodeSource::Community,
            'assertion' => 'available',
            'evidence_classification' => GiftCodeEvidenceClassification::CommunityClaim,
            'verification_state' => GiftCodeEvidenceVerificationState::Unverified,
            'observed_at' => now(),
            'fingerprint' => hash('sha256', 'gcw-contrib'),
        ]);

        $result = app(RebuildGiftCodeContributorProjections::class)->handle(10);
        self::assertSame(1, $result['updated']);
        $projection = GiftCodeContributorProjection::query()->findOrFail($account->userId);
        self::assertSame(1, $projection->corroborated_count);
        self::assertSame(0, $projection->accepted_count);
        self::assertFalse(app(AllianceRankPermissions::class)->allows(AllianceRank::R5, AlliancePermission::GiftCodeCoverage));
    }

    public function test_large_eligibility_scan_is_query_bounded_before_item_limit_rejection(): void
    {
        config()->set('game_world.gift_codes.redemption_workspace', true);
        config()->set('game_world.gift_codes.max_session_codes', 100);
        config()->set('game_world.gift_codes.max_session_governors', 20);
        config()->set('game_world.gift_codes.max_session_items', 500);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        for ($index = 0; $index < 20; $index++) {
            $scenarios->player($account->userId, 2200 + $index, 'GCW-LOAD-'.$index);
        }
        for ($index = 0; $index < 100; $index++) {
            $this->validCode(sprintf('GCW-LOAD-%03d', $index));
        }
        $actor = User::query()->findOrFail($account->userId);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            app(CreateGiftCodeRedemptionSession::class)->handle($actor, GiftCodeRedemptionSessionMode::AllActionable);
            self::fail('A 2,000-pair run must respect the configured 500-item bound.');
        } catch (ValidationException) {
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            self::assertLessThanOrEqual(10, $queries);
            self::assertSame(0, GiftCodeRedemptionSessionItem::query()->count());
        }
    }

    private function validCode(string $code): GiftCode
    {
        return GiftCode::query()->create([
            'code' => $code,
            'normalized_code' => strtoupper($code),
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

    private function redemption(
        GiftCode $code,
        PlayerReference $player,
        GiftCodeRedemptionStatus $status,
    ): GiftCodeRedemption {
        return GiftCodeRedemption::query()->create([
            'gift_code_id' => $code->id,
            'player_id' => $player->playerId,
            'kingdom_id' => $player->kingdomId,
            'status' => $status,
            'provider' => 'workspace-test',
            'attempts' => 1,
            'last_result_code' => $status->value,
            'last_attempt_at' => now(),
            'redeemed_at' => $status->successful() ? now() : null,
        ]);
    }
}
