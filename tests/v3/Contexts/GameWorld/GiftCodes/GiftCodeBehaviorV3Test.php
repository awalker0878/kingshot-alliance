<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\GameWorld\GiftCodes\Actions\IngestApprovedGiftCodeObservation;
use App\Contexts\GameWorld\GiftCodes\Actions\ModerateGiftCode;
use App\Contexts\GameWorld\GiftCodes\Actions\PrepareGiftCodeRedemptions;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeExpiryNotifications;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeTransitionNotifications;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeFacts;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeSourcePolicyChanges;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordObservedGiftCodeRedemptionResult;
use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceReconciliationJob;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeCatalogQuery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;
use UnexpectedValueException;

final class GiftCodeBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_submissions_cannot_claim_official_authority_and_provenance_is_append_only(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);

        try {
            app(SubmitGiftCode::class)->handle($player, [
                'code' => 'SPOOFED-OFFICIAL',
                'source_type' => 'official',
            ]);
            self::fail('Ordinary submissions must not be able to assert official authority.');
        } catch (ValidationException) {
            self::assertSame(0, GiftCode::query()->count());
        }

        $first = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'COMMUNITY-CODE',
            'source_type' => 'manual',
        ]);
        $duplicate = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'community-code',
            'source_type' => 'manual',
        ]);
        $additional = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'COMMUNITY-CODE',
            'source_type' => 'community',
            'source_url' => 'https://community.example.test/post/1',
        ]);

        self::assertFalse($first->duplicateDetected);
        self::assertTrue($duplicate->duplicateDetected);
        self::assertFalse($duplicate->provenanceAdded);
        self::assertTrue($additional->provenanceAdded);
        self::assertSame(2, GiftCodeProvenance::query()->count());
        self::assertSame(GiftCodeStatus::Pending, GiftCode::query()->firstOrFail()->status);

        $evidence = GiftCodeProvenance::query()->firstOrFail();
        $evidence->source_label = 'Rewritten evidence';
        $this->expectException(LogicException::class);
        $evidence->save();
    }

    public function test_approved_ingestion_is_idempotent_and_rejects_misleading_source_urls(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->source('official-one', 'official-one.example.test');
        $observation = $this->observation('TRUSTED-CODE', sourceUrl: 'https://official-one.example.test/gifts/1');

        $first = app(IngestApprovedGiftCodeObservation::class)->handle($source, $observation);
        $replay = app(IngestApprovedGiftCodeObservation::class)->handle($source, $observation);

        self::assertTrue($first['accepted']);
        self::assertFalse($first['duplicate']);
        self::assertTrue($replay['duplicate']);
        self::assertSame(1, GiftCodeProvenance::query()->count());
        $giftCode = GiftCode::query()->findOrFail($first['gift_code_id']);
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(1, $giftCode->status_revision);

        $this->expectException(ValidationException::class);
        app(IngestApprovedGiftCodeObservation::class)->handle(
            $source,
            $this->observation('EVIL-CODE', sourceUrl: 'https://lookalike.example.test/gifts/2'),
        );
    }

    public function test_conflicting_expiry_stays_disputed_until_an_authorized_resolution_and_all_revisions_emit(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.moderation', true);
        $firstSource = $this->source('expiry-one', 'expiry-one.example.test');
        $secondSource = $this->source('expiry-two', 'expiry-two.example.test');
        $firstExpiry = now()->addDays(2)->startOfHour()->toIso8601String();
        $secondExpiry = now()->addDays(3)->startOfHour()->toIso8601String();

        $first = app(IngestApprovedGiftCodeObservation::class)->handle(
            $firstSource,
            $this->observation(
                'EXPIRY-CONFLICT',
                sourceUrl: 'https://expiry-one.example.test/gift',
                claimedExpiresAt: $firstExpiry,
                contentFingerprint: hash('sha256', 'expiry-one'),
            ),
        );
        $giftCode = GiftCode::query()->findOrFail($first['gift_code_id']);
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(1, $giftCode->status_revision);
        self::assertSame(1, $giftCode->expires_revision);

        app(IngestApprovedGiftCodeObservation::class)->handle(
            $secondSource,
            $this->observation(
                'EXPIRY-CONFLICT',
                sourceUrl: 'https://expiry-two.example.test/gift',
                claimedExpiresAt: $secondExpiry,
                contentFingerprint: hash('sha256', 'expiry-two'),
            ),
        );
        $giftCode->refresh();
        self::assertSame(GiftCodeStatus::Disputed, $giftCode->status);
        self::assertSame('credible_expiry_conflict', $giftCode->status_reason_code);
        self::assertSame(2, $giftCode->status_revision);
        self::assertSame(2, $giftCode->expires_revision);

        $actor = $this->administrator();
        app(ModerateGiftCode::class)->handle(
            $actor,
            (string) $giftCode->id,
            GiftCodeModerationAction::CorrectExpiry,
            'Resolve the two qualified publication dates.',
            [],
            null,
            ['expires_at' => $secondExpiry, 'expiry_precision' => 'hour'],
        );
        $giftCode->refresh();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(3, $giftCode->status_revision);
        self::assertSame(3, $giftCode->expires_revision);

        $statusRevisions = OutboxMessage::query()
            ->where('event_type', 'gift_code.status_changed')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (OutboxMessage $message): int => (int) ($message->payload['status_revision'] ?? -1))
            ->all();
        self::assertSame([1, 2, 3], $statusRevisions);
    }

    public function test_multi_governor_handoff_is_owner_scoped_and_outcomes_do_not_invent_global_applicability(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $firstGovernor = $scenarios->player($account->userId, 1501, 'GOV-1501-A');
        $secondGovernor = $scenarios->player($account->userId, 1502, 'GOV-1502-B');
        $foreignGovernor = $scenarios->player($other->userId, 1503, 'GOV-1503-C');
        $source = $this->source('redemption-source', 'redemption.example.test');
        $ingested = app(IngestApprovedGiftCodeObservation::class)->handle(
            $source,
            $this->observation('MULTI-GOVERNOR', sourceUrl: 'https://redemption.example.test/gift'),
        );
        $actor = User::query()->findOrFail($account->userId);

        $prepared = app(PrepareGiftCodeRedemptions::class)->handle(
            $actor,
            $account->userId,
            $ingested['gift_code_id'],
            [$firstGovernor->playerId, $secondGovernor->playerId, $foreignGovernor->playerId],
        )->toArray();

        self::assertSame(2, $prepared['succeeded']);
        self::assertSame(1, $prepared['failed']);
        self::assertSame([$foreignGovernor->playerId], $prepared['failedItemIds']);
        self::assertSame(2, GiftCodeRedemption::query()->count());

        app(RecordObservedGiftCodeRedemptionResult::class)->handle(
            $ingested['gift_code_id'],
            $firstGovernor,
            'wrong_kingdom',
        );
        $completed = app(RecordObservedGiftCodeRedemptionResult::class)->handle(
            $ingested['gift_code_id'],
            $secondGovernor,
            'redeemed',
        );
        $terminalReplay = app(RecordObservedGiftCodeRedemptionResult::class)->handle(
            $ingested['gift_code_id'],
            $secondGovernor,
            'invalid',
        );

        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $completed->status);
        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $terminalReplay->status);
        self::assertSame($completed->attempts, $terminalReplay->attempts);
        self::assertSame(GiftCodeStatus::Valid, GiftCode::query()->findOrFail($ingested['gift_code_id'])->status);
        self::assertDatabaseMissing('gift_code_fact_projections', [
            'gift_code_id' => $ingested['gift_code_id'],
            'fact_type' => 'applicability',
            'qualified' => true,
        ]);
    }

    public function test_reward_and_applicability_are_promoted_only_from_qualified_non_conflicting_evidence(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $firstSource = $this->source('facts-one', 'facts-one.example.test');
        $secondSource = $this->source('facts-two', 'facts-two.example.test');

        $created = app(IngestApprovedGiftCodeObservation::class)->handle(
            $firstSource,
            $this->observation('FACT-CODE', sourceUrl: 'https://facts-one.example.test/gift'),
        );
        app(ReconcileGiftCodeFacts::class)->handle($created['gift_code_id']);
        $unknownReward = GiftCodeFactProjection::query()
            ->where('gift_code_id', $created['gift_code_id'])
            ->where('fact_type', 'reward')
            ->firstOrFail();
        self::assertFalse($unknownReward->qualified);
        self::assertSame('reward_details_unknown', $unknownReward->reason_code);

        app(IngestApprovedGiftCodeObservation::class)->handle(
            $firstSource,
            $this->observation(
                'FACT-CODE',
                assertion: 'reward',
                assertionPayload: ['items' => [['name' => 'Wood', 'quantity' => 1000]]],
                sourceUrl: 'https://facts-one.example.test/gift',
                contentFingerprint: hash('sha256', 'reward-one'),
            ),
        );
        $reward = $unknownReward->fresh();
        self::assertTrue($reward?->qualified ?? false);
        self::assertSame('Wood', $reward?->value['items'][0]['name'] ?? null);

        app(IngestApprovedGiftCodeObservation::class)->handle(
            $firstSource,
            $this->observation(
                'FACT-CODE',
                assertion: 'applicability',
                assertionPayload: ['kingdoms' => [1501]],
                sourceUrl: 'https://facts-one.example.test/gift',
                contentFingerprint: hash('sha256', 'applicability-one'),
            ),
        );
        app(IngestApprovedGiftCodeObservation::class)->handle(
            $secondSource,
            $this->observation(
                'FACT-CODE',
                assertion: 'applicability',
                assertionPayload: ['kingdoms' => [1502]],
                sourceUrl: 'https://facts-two.example.test/gift',
                contentFingerprint: hash('sha256', 'applicability-two'),
            ),
        );
        $applicability = GiftCodeFactProjection::query()
            ->where('gift_code_id', $created['gift_code_id'])
            ->where('fact_type', 'applicability')
            ->firstOrFail();
        self::assertFalse($applicability->qualified);
        self::assertSame('credible_applicability_conflict', $applicability->reason_code);
    }

    public function test_notification_fanout_respects_preferences_and_revisions(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.notification_fanout', true);
        config()->set('game_world.gift_codes.moderation', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $governor = $scenarios->player($account->userId, 1601, 'GOV-1601-A');
        $source = $this->source('notification-source', 'notifications.example.test');
        $expiry = now()->addHours(12)->startOfMinute()->toIso8601String();

        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $governor->playerId,
            'gift_code.expiring',
            DeliveryChannel::InApp,
            false,
        );
        $ingested = app(IngestApprovedGiftCodeObservation::class)->handle(
            $source,
            $this->observation(
                'NOTIFY-CODE',
                sourceUrl: 'https://notifications.example.test/gift',
                claimedExpiresAt: $expiry,
            ),
        );

        $availability = app(QueueGiftCodeTransitionNotifications::class)->handle();
        self::assertSame(1, $availability->createdDeliveryCount);
        self::assertSame(1, NotificationDelivery::query()->where('notification_type', 'gift_code.available')->count());

        app(PrepareGiftCodeRedemptions::class)->handle(
            User::query()->findOrFail($account->userId),
            $account->userId,
            $ingested['gift_code_id'],
            [$governor->playerId],
        );
        self::assertSame(0, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);

        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $governor->playerId,
            'gift_code.expiring',
            DeliveryChannel::InApp,
            true,
        );
        self::assertSame(1, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);
        self::assertSame(0, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);

        $actor = $this->administrator();
        app(ModerateGiftCode::class)->handle(
            $actor,
            $ingested['gift_code_id'],
            GiftCodeModerationAction::CorrectExpiry,
            'Qualified source clarified the expiry minute.',
            [],
            null,
            ['expires_at' => now()->addHours(10)->startOfMinute()->toIso8601String(), 'expiry_precision' => 'minute'],
        );
        self::assertSame(1, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);
        self::assertSame(2, NotificationDelivery::query()->where('notification_type', 'gift_code.expiring')->count());
    }

    public function test_ingestion_runner_is_bounded_idempotent_and_records_parser_failure_health(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.ingestion_batch_size', 10);
        $source = $this->source('adapter-source', 'adapter.example.test', 'stable-adapter');
        $observation = $this->observation('ADAPTER-CODE', sourceUrl: 'https://adapter.example.test/gift');
        $stable = new class($observation) implements GiftCodeSourceAdapter
        {
            public function __construct(private readonly GiftCodeIngestionObservation $observation) {}

            public function key(): string
            {
                return 'stable-adapter';
            }

            public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
            {
                return new GiftCodeIngestionPage([$this->observation], 'cursor-1');
            }
        };
        $broken = new class implements GiftCodeSourceAdapter
        {
            public function key(): string
            {
                return 'broken-adapter';
            }

            public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
            {
                throw new UnexpectedValueException('The parser rejected an unsupported source format.');
            }
        };
        $runner = new RunApprovedGiftCodeSourceIngestion(
            new GiftCodeSourceAdapterRegistry([$stable, $broken]),
            app(IngestApprovedGiftCodeObservation::class),
        );

        $first = $runner->handle(sourceKey: $source->source_key);
        $replay = $runner->handle(sourceKey: $source->source_key);
        self::assertSame(1, $first->accepted);
        self::assertSame(1, $replay->duplicates);
        self::assertSame(1, GiftCodeProvenance::query()->count());

        $failedSource = $this->source('broken-source', 'broken.example.test', 'broken-adapter');
        $failure = $runner->handle(sourceKey: $failedSource->source_key);
        self::assertSame(1, $failure->failedSources);
        self::assertSame(
            'unsupported_source_format',
            GiftCodeIngestionRun::query()
                ->where('gift_code_source_id', $failedSource->id)
                ->latest('started_at')
                ->value('failure_code'),
        );
    }

    public function test_revoked_source_is_reconciled_without_rewriting_its_evidence(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->source('revocable-source', 'revocable.example.test');
        $ingested = app(IngestApprovedGiftCodeObservation::class)->handle(
            $source,
            $this->observation('REVOKED-CODE', sourceUrl: 'https://revocable.example.test/gift'),
        );
        self::assertSame(GiftCodeStatus::Valid, GiftCode::query()->findOrFail($ingested['gift_code_id'])->status);

        $actor = $this->administrator();
        app(\App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry::class)
            ->revoke($actor, (string) $source->id, 'The source lost its platform approval.');
        self::assertSame(1, GiftCodeSourceReconciliationJob::query()->count());

        $result = app(ReconcileGiftCodeSourcePolicyChanges::class)->handle();
        self::assertTrue($result['completed']);
        self::assertSame(GiftCodeStatus::Pending, GiftCode::query()->findOrFail($ingested['gift_code_id'])->status);
        self::assertSame(1, GiftCodeProvenance::query()->count());
    }

    public function test_alliance_authority_does_not_grant_platform_source_authority(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $scenarios->alliance($player);
        $identity = app(AccountIdentityQuery::class)->require($account->userId);

        $this->expectException(AuthorizationException::class);
        app(\App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry::class)->register($identity, [
            'source_key' => 'alliance-claimed-source',
            'name' => 'Alliance claimed source',
            'classification' => 'official',
            'canonical_domain' => 'alliance.example.test',
            'verification_method' => 'manual_review',
            'ingestion_enabled' => false,
        ]);
    }

    public function test_catalogue_pagination_and_detail_history_keep_constant_query_budgets(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $governor = $scenarios->player($account->userId);
        $detailCode = null;

        for ($index = 0; $index < 80; $index++) {
            $giftCode = GiftCode::query()->create([
                'code' => sprintf('BUDGET-%03d', $index),
                'normalized_code' => sprintf('BUDGET-%03d', $index),
                'status' => GiftCodeStatus::Valid,
                'status_revision' => 1,
                'status_reason_code' => 'qualified_positive_evidence',
                'status_evidence_ids' => [],
                'status_changed_at' => now(),
                'status_derived_at' => now(),
                'discovered_at' => now()->subSeconds($index),
                'expires_revision' => 0,
            ]);
            $detailCode ??= $giftCode;
        }
        self::assertInstanceOf(GiftCode::class, $detailCode);

        for ($index = 0; $index < 120; $index++) {
            GiftCodeProvenance::query()->create([
                'gift_code_id' => (string) $detailCode->id,
                'submitted_by_player_id' => $governor->playerId,
                'source_type' => GiftCodeSource::Community,
                'source_label' => 'History '.$index,
                'assertion' => 'available',
                'evidence_classification' => GiftCodeEvidenceClassification::CommunityClaim,
                'verification_state' => GiftCodeEvidenceVerificationState::Unverified,
                'observed_at' => now()->subSeconds($index),
                'fingerprint' => hash('sha256', 'history-'.$index),
            ]);
        }

        $catalogue = app(GiftCodeCatalogQuery::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = $catalogue->pageForPlayers([$governor->playerId], ['view' => 'active'], 25);
        $indexQueries = count(DB::getQueryLog());
        self::assertCount(25, $page->items());
        self::assertNotNull($page->nextCursor());
        self::assertLessThanOrEqual(4, $indexQueries);

        DB::flushQueryLog();
        $detail = $catalogue->detailForPlayers((string) $detailCode->id, [$governor->playerId]);
        $detailQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertCount(120, $detail->provenances);
        self::assertLessThanOrEqual(7, $detailQueries);
    }

    private function source(string $key, string $domain, ?string $adapterKey = null): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create([
            'source_key' => $key,
            'name' => str_replace('-', ' ', ucfirst($key)),
            'classification' => 'official',
            'canonical_domain' => $domain,
            'is_active' => true,
            'verification_method' => 'signed_publication',
            'adapter_key' => $adapterKey,
            'policy_revision' => 1,
            'provenance_policy' => ['auto_verify' => true],
            'ingestion_enabled' => true,
        ]);
    }

    /** @param array<string,mixed>|null $assertionPayload */
    private function observation(
        string $code,
        string $assertion = 'available',
        ?array $assertionPayload = null,
        ?string $sourceUrl = null,
        ?string $claimedExpiresAt = null,
        ?string $contentFingerprint = null,
        bool $verificationPassed = true,
    ): GiftCodeIngestionObservation {
        return new GiftCodeIngestionObservation(
            code: $code,
            assertion: $assertion,
            assertionPayload: $assertionPayload,
            sourceUrl: $sourceUrl,
            claimedExpiresAt: $claimedExpiresAt,
            expiryPrecision: $claimedExpiresAt === null ? null : 'hour',
            expiryTimezone: $claimedExpiresAt === null ? null : 'UTC',
            publishedAt: now()->subMinute()->toIso8601String(),
            sourceVersion: 'source-v1',
            retrievalVersion: 'retrieval-v1',
            parserVersion: 'parser-v1',
            contentFingerprint: $contentFingerprint ?? hash('sha256', implode('|', [
                $code,
                $assertion,
                json_encode($assertionPayload, JSON_THROW_ON_ERROR),
                $claimedExpiresAt ?? '',
            ])),
            rawEvidenceRef: 'evidence://gift-code/'.strtolower($code).'/'.$assertion,
            verificationPassed: $verificationPassed,
        );
    }

    private function administrator(): AccountIdentity
    {
        $account = app(ScenarioFactory::class)->account();
        User::query()->whereKey($account->userId)->update([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        app(ManagePlatformAdministrator::class)->grant($account->userId);

        return app(AccountIdentityQuery::class)->require($account->userId);
    }
}
