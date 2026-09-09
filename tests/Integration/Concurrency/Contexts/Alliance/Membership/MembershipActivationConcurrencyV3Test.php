<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Membership;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayerAccount;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class MembershipActivationConcurrencyV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function orders(): iterable
    {
        yield 'first Alliance wins' => [true];
        yield 'second Alliance wins' => [false];
    }

    #[DataProvider('orders')]
    public function test_competing_alliances_preserve_one_winner_and_a_usable_caller_transaction(bool $firstWins): void
    {
        [$owner, $alliance, $target, $membership] = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $otherOwner = $factory->player($factory->account()->userId, 59272);
        $otherAlliance = $factory->alliance($otherOwner);
        $otherMembership = $this->membership($otherAlliance, $target);
        $first = static fn () => app(UpdateMembershipStatus::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, MembershipStatus::Active);
        $second = static fn () => app(UpdateMembershipStatus::class)->handle($otherAlliance->allianceId, $otherOwner->playerId, (string) $otherMembership->id, MembershipStatus::Active);
        $primary = $this->competitor();
        $won = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $firstWins, $first, $second, &$won): void {
            if ($won || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select exists(')
                || ! str_contains($query->sql, '"alliance_memberships"') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $won = true;
            DB::setDefaultConnection('activation_writer');
            try {
                $firstWins ? $first() : $second();
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            DB::transaction(static function () use ($firstWins, $first, $second): void {
                try {
                    $firstWins ? $second() : $first();
                    self::fail('A concurrent active membership must become ordinary validation.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('status', $exception->errors());
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
            });
            self::assertTrue($won);
            self::assertSame($firstWins ? MembershipStatus::Active : MembershipStatus::Suspended, $membership->fresh()?->status);
            self::assertSame($firstWins ? MembershipStatus::Suspended : MembershipStatus::Active, $otherMembership->fresh()?->status);
            self::assertSame(1, AllianceMembership::query()->where('player_id', $target->playerId)->where('status', 'active')->count());
            self::assertSame(1, DB::table('audit_events')->where('event', 'membership.status_changed')->count());
            self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'member.updated')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('activation_writer');
        }
    }

    /** @return iterable<string,array{string,bool}> */
    public static function identityChanges(): iterable
    {
        foreach (['move', 'release', 'reconcile'] as $change) {
            yield 'activation before '.$change => [$change, true];
            yield $change.' before activation' => [$change, false];
        }
    }

    #[DataProvider('identityChanges')]
    public function test_activation_and_identity_changes_use_current_facts_in_both_orders(string $operation, bool $activationFirst): void
    {
        [$owner, $alliance, $target, $membership] = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $destination = $factory->kingdom(59273);
        $canonical = app(PersistPlayerIdentity::class)->handle($target->kingdomId, 'Canonical Identity', null);
        $activate = static fn () => app(UpdateMembershipStatus::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, MembershipStatus::Active);
        $change = static fn () => match ($operation) {
            'move' => app(PersistPlayerIdentity::class)->handle($destination->kingdomId, $target->currentName, $target->gamePlayerId, $target->playerId, PlayerIdentitySource::Import),
            'release' => app(ReleasePlayerAccount::class)->handle($target->userId ?? throw new RuntimeException('Expected owner.'), $target->playerId),
            'reconcile' => app(ReconcilePlayers::class)->handle($canonical->playerId, $target->playerId, 'Confirmed duplicate'),
        };
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $activationFirst, $activate, $change, &$attempted): void {
            $barrier = $activationFirst
                ? str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, 'for share')
                : str_starts_with($query->sql, 'update "players"');
            if ($attempted || $query->connectionName !== $primary || ! $barrier || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('activation_writer');
            try {
                try {
                    $activationFirst ? $change() : $activate();
                    self::fail('Identity and admission must serialize on the current Player.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $activationFirst ? $activate() : $change();
            self::assertTrue($attempted);
            DB::setDefaultConnection('activation_writer');
            if (! $activationFirst && $operation === 'release') {
                // Membership belongs to durable Player identity, not account ownership.
                $activate();
                self::assertNull(Player::query()->findOrFail($target->playerId)->user_id);
            } else {
                try {
                    $activationFirst ? $change() : $activate();
                    self::fail('The later writer must respect the committed lifecycle change.');
                } catch (ValidationException|ModelNotFoundException $exception) {
                    self::assertTrue($exception instanceof ValidationException || $operation === 'reconcile');
                }
            }
            self::assertSame($activationFirst || $operation === 'release' ? MembershipStatus::Active : MembershipStatus::Suspended, $membership->fresh()?->status);
            if (! $activationFirst && $operation === 'move') {
                self::assertSame($destination->kingdomId, Player::query()->findOrFail($target->playerId)->current_kingdom_id);
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('activation_writer');
        }
    }

    public function test_an_unrelated_unique_failure_propagates_and_preserves_the_caller_transaction(): void
    {
        [$owner, $alliance, , $membership] = $this->fixture();
        $ownerMembership = AllianceMembership::query()->where('player_id', $owner->playerId)->firstOrFail();
        $before = $this->state();
        AllianceMembership::updating(static function (AllianceMembership $updated) use ($membership, $ownerMembership): void {
            if ((string) $updated->id === (string) $membership->id) {
                $updated->id = $ownerMembership->id;
            }
        });
        DB::transaction(static function () use ($owner, $alliance, $membership): void {
            try {
                app(UpdateMembershipStatus::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, MembershipStatus::Active);
                self::fail('A primary-key fault must not be disguised as competing admission.');
            } catch (UniqueConstraintViolationException $exception) {
                self::assertSame('23505', $exception->errorInfo[0] ?? null);
            }
            self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
        });
        self::assertSame($before, $this->state());
    }

    public function test_a_late_outbox_failure_rolls_back_activation_and_audit(): void
    {
        [$owner, $alliance, , $membership] = $this->fixture();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected activation outbox failure.');
            }
        });
        try {
            app(UpdateMembershipStatus::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, MembershipStatus::Active);
            self::fail('Activation effects must roll back together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected activation outbox failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{PlayerReference,AllianceReference,PlayerReference,AllianceMembership} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59272);
        $target = $factory->player($factory->account()->userId, 59272);
        $alliance = $factory->alliance($owner);

        return [$owner, $alliance, $target, $this->membership($alliance, $target)];
    }

    private function membership(AllianceReference $alliance, PlayerReference $player): AllianceMembership
    {
        return AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $player->playerId, 'status' => MembershipStatus::Suspended, 'rank' => AllianceRank::R1, 'joined_at' => now()]);
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.activation_writer', array_replace(DB::connection()->getConfig(), ['name' => 'activation_writer']));
        DB::connection('activation_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        return [
            'memberships' => DB::table('alliance_memberships')->orderBy('id')->get()->toJson(),
            'audit' => DB::table('audit_events')->orderBy('id')->get()->toJson(),
            'outbox' => DB::table('outbox_messages')->orderBy('id')->get()->toJson(),
        ];
    }
}
