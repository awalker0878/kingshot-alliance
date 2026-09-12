<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Platform\Integrations\Actions\ClaimExternalActorLink;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\IssueExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Actions\RevokeApiCredential;
use App\Contexts\Platform\Integrations\Actions\RevokeExternalActorLink;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Exceptions\ExternalActionBusy;
use App\Workflows\ExternalEventParticipation\Actions\ExecuteExternalEventParticipation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class ExternalActorScopeOrderingTest extends TestCase
{
    use DatabaseTruncation;

    public static function competingOrders(): iterable
    {
        foreach (['credential', 'link'] as $kind) {
            foreach (['response', 'registration'] as $operation) {
                yield $kind.' '.$operation.' admission first' => [$kind, true, $operation];
                yield $kind.' '.$operation.' revocation first' => [$kind, false, $operation];
            }
        }
    }

    #[DataProvider('competingOrders')]
    public function test_admission_and_revocation_share_the_owner_scope_order(string $kind, bool $admissionFirst, string $operation): void
    {
        $f = $this->fixture();
        $primary = DB::getDefaultConnection();
        // Eloquent persists hydrated models through the connection's configured name.
        config()->set('database.connections.external_revoker', [...DB::connection()->getConfig(), 'name' => 'external_revoker']);
        DB::connection()->statement("SET lock_timeout = '150ms'");
        DB::connection('external_revoker')->statement("SET lock_timeout = '150ms'");
        $respond = fn () => $operation === 'response' ? $this->respond($f)
            : app(ExecuteExternalEventParticipation::class)->registration($f['allianceId'], $f['credentialId'], ExternalActorProvider::Discord,
                $f['subject'], 'scope-order-registration', $f['occurrenceId'], true);
        $revoke = static fn () => $kind === 'credential'
            ? app(RevokeApiCredential::class)->handle($f['allianceId'], $f['playerId'], $f['credentialId'])
            : app(RevokeExternalActorLink::class)->handle($f['allianceId'], $f['playerId'], $f['linkId']);
        $attempted = false;
        $credentialAcquiredBeforeWait = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $admissionFirst, $respond, $revoke, &$attempted, &$credentialAcquiredBeforeWait): void {
            if ($query->connectionName === $primary && str_contains($query->sql, 'from "api_credentials"') && str_contains($query->sql, 'for update')) {
                $credentialAcquiredBeforeWait = true;
            }
            $firstConnection = $admissionFirst ? $primary : 'external_revoker';
            if ($attempted || $query->connectionName !== $firstConnection
                || ! str_starts_with($query->sql, 'select * from "alliances"')
                || (! str_contains($query->sql, 'for update') && ! str_contains($query->sql, 'for share'))) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection($admissionFirst ? 'external_revoker' : $primary);
            try {
                try {
                    $admissionFirst ? $revoke() : $respond();
                    self::fail('The competing operation must wait at current Alliance scope.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                if (! $admissionFirst) {
                    // The old credential-first callback order fails this regression.
                    self::assertFalse($credentialAcquiredBeforeWait);
                }
            } finally {
                DB::setDefaultConnection($firstConnection);
            }
        });
        try {
            DB::setDefaultConnection($admissionFirst ? $primary : 'external_revoker');
            $admissionFirst ? $respond() : $revoke();
            self::assertTrue($attempted);
            DB::setDefaultConnection('external_revoker');
            if ($admissionFirst) {
                $revoke();
            }
            DB::setDefaultConnection($primary);
            try {
                $respond();
                self::fail('Committed revocation must reject admission or replay.');
            } catch (ValidationException|ModelNotFoundException) {
                self::assertSame($admissionFirst ? 1 : 0, DB::table($operation === 'response' ? 'event_responses' : 'event_registrations')->count());
                self::assertSame($admissionFirst ? 1 : 0, DB::table('external_actor_action_receipts')->count());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::connection()->statement('SET lock_timeout = 0');
            DB::purge('external_revoker');
        }
    }

    public function test_late_receipt_failure_rolls_back_owner_effects_and_retry_remains_idempotent(): void
    {
        $f = $this->fixture();
        $before = $this->counts();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'update "external_actor_action_receipts"')) {
                $failed = true;
                throw new RuntimeException('Injected receipt failure.');
            }
        });
        try {
            $this->respond($f);
            self::fail('Receipt completion must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected receipt failure.', $exception->getMessage());
        }
        self::assertSame($before, $this->counts());
        $this->respond($f);
        $after = $this->counts();
        $this->respond($f);
        self::assertSame($after, $this->counts());
        self::assertSame(1, DB::table('event_responses')->count());
        self::assertSame(1, DB::table('external_actor_action_receipts')->count());
    }

    public function test_replay_rechecks_current_participation_membership(): void
    {
        $f = $this->fixture();
        $this->respond($f);
        DB::table('alliance_memberships')->where('alliance_id', $f['allianceId'])->where('player_id', $f['playerId'])->update(['status' => 'left']);
        $before = $this->counts();
        try {
            $this->respond($f);
            self::fail('A stale receipt must not bypass current Event participation authority.');
        } catch (AuthorizationException) {
            self::assertSame($before, $this->counts());
        }
    }

    public function test_a_competing_pairing_claim_causes_retry_without_partial_participation(): void
    {
        $f = $this->fixture();
        $pairing = app(IssueExternalActorPairingCode::class)->handle($f['allianceId'], $f['playerId'], ExternalActorProvider::Discord);
        $newSubject = '223456789012345678';
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.external_pairing', [...DB::connection()->getConfig(), 'name' => 'external_pairing']);
        DB::connection()->statement("SET lock_timeout = '150ms'");
        $attempted = false;
        DB::listen(function (QueryExecuted $query) use ($primary, $f, &$attempted): void {
            if ($attempted || $query->connectionName !== 'external_pairing'
                || ! str_contains($query->sql, 'from "api_credentials"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection($primary);
            try {
                try {
                    $this->respond($f);
                    self::fail('The held pairing credential must produce a retryable conflict.');
                } catch (ExternalActionBusy) {
                    self::assertSame(0, DB::table('event_responses')->count());
                    self::assertSame(0, DB::table('external_actor_action_receipts')->count());
                }
            } finally {
                DB::setDefaultConnection('external_pairing');
            }
        });
        try {
            DB::setDefaultConnection('external_pairing');
            app(ClaimExternalActorLink::class)->handle($f['allianceId'], $f['credentialId'], ExternalActorProvider::Discord, $newSubject, $pairing->code);
            self::assertTrue($attempted);
            DB::setDefaultConnection($primary);
            $f['subject'] = $newSubject;
            $this->respond($f);
            self::assertSame(1, DB::table('event_responses')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::connection()->statement('SET lock_timeout = 0');
            DB::purge('external_pairing');
        }
    }

    public function test_http_link_contention_returns_conflict_and_the_same_request_can_retry(): void
    {
        $f = $this->fixture();
        config()->set('database.connections.external_link_holder', [...DB::connection()->getConfig(), 'name' => 'external_link_holder']);
        $holder = DB::connection('external_link_holder');
        try {
            $holder->beginTransaction();
            $holder->table('external_actor_links')->where('id', $f['linkId'])->lockForUpdate()->first();
            $payload = ['provider' => 'discord', 'external_subject' => $f['subject'], 'response' => 'going'];
            $this->withToken($f['apiToken'])->withHeader('Idempotency-Key', 'http-scope-contention')
                ->putJson('/api/v1/me/events/'.$f['occurrenceId'].'/response', $payload)
                ->assertConflict()->assertHeader('Retry-After', '1');
            self::assertSame(0, DB::table('event_responses')->count());
            self::assertSame(0, DB::table('external_actor_action_receipts')->count());
            $holder->rollBack();
            $this->putJson('/api/v1/me/events/'.$f['occurrenceId'].'/response', $payload)
                ->assertOk()->assertJsonPath('meta.replayed', false);
            $this->putJson('/api/v1/me/events/'.$f['occurrenceId'].'/response', $payload)
                ->assertOk()->assertJsonPath('meta.replayed', true);
            self::assertSame(1, DB::table('event_responses')->count());
        } finally {
            if ($holder->transactionLevel() > 0) {
                $holder->rollBack();
            }
            DB::purge('external_link_holder');
        }
    }

    /** @param array<string,string> $f */
    private function respond(array $f): void
    {
        app(ExecuteExternalEventParticipation::class)->respond($f['allianceId'], $f['credentialId'], ExternalActorProvider::Discord,
            $f['subject'], 'scope-order-response', $f['occurrenceId'], EventResponseChoice::Going);
    }

    /** @return array<string,string> */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId, 64111);
        $alliance = $factory->alliance($player);
        $credential = app(CreateApiCredential::class)->handle($alliance->allianceId, $player->playerId,
            'Scope order', ['actor-links:write', 'event-participation:write']);
        $pairing = app(IssueExternalActorPairingCode::class)->handle($alliance->allianceId, $player->playerId, ExternalActorProvider::Discord);
        $subject = '123456789012345678';
        $link = app(ClaimExternalActorLink::class)->handle($alliance->allianceId, $credential->credentialId, ExternalActorProvider::Discord, $subject, $pairing->code);
        $configuration = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $created = app(CreateEvent::class)->handle(actorPlayerId: $player->playerId, configurationId: (string) $configuration->id,
            scope: EventScope::Alliance, targetId: $alliance->allianceId, firstLocalStart: CarbonImmutable::now('UTC')->addHour(),
            title: 'External scope order', durationMinutes: 60);

        return ['allianceId' => $alliance->allianceId, 'playerId' => $player->playerId, 'credentialId' => $credential->credentialId, 'apiToken' => $credential->token,
            'linkId' => $link->linkId, 'subject' => $subject, 'occurrenceId' => (string) $created->firstOccurrenceId];
    }

    /** @return array<string,int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['event_responses', 'external_actor_action_receipts', 'audit_events', 'outbox_messages'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
