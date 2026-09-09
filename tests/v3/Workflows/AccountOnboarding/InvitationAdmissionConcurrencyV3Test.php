<?php

declare(strict_types=1);

namespace Tests\v3\Workflows\AccountOnboarding;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\TransitionAllianceLifecycle;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\MarkRosterEntryLeft;
use App\Contexts\Alliance\Membership\Actions\RevokeInvitation;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use App\Workflows\AccountOnboarding\Actions\AcceptInvitationForAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class InvitationAdmissionConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function admissionOrders(): iterable
    {
        yield 'acceptance first' => [true];
        yield 'activation first' => [false];
    }

    #[DataProvider('admissionOrders')]
    public function test_acceptance_and_foreign_alliance_activation_preserve_one_winner(bool $acceptanceFirst): void
    {
        $fixture = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $otherOwner = $factory->player($factory->account()->userId, 59274);
        $otherAlliance = $factory->alliance($otherOwner);
        $otherMembership = AllianceMembership::query()->create([
            'alliance_id' => $otherAlliance->allianceId, 'player_id' => $fixture['playerId'],
            'status' => MembershipStatus::Suspended, 'rank' => AllianceRank::R1, 'joined_at' => now(),
        ]);
        $accept = static fn () => app(AcceptInvitationForAccount::class)->handle($fixture['userId'], $fixture['token']);
        $activate = static fn () => app(UpdateMembershipStatus::class)->handle($otherAlliance->allianceId, $otherOwner->playerId, (string) $otherMembership->id, MembershipStatus::Active);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $fixture, $acceptanceFirst, $accept, $activate, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"')
                || ! str_contains($query->sql, $acceptanceFirst ? 'for update' : 'for share') || ! in_array($fixture['playerId'], $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('invitation_writer');
            try {
                try {
                    $acceptanceFirst ? $activate() : $accept();
                    self::fail('Admission must wait for the Player identity barrier.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $acceptanceFirst ? $accept() : $activate();
            self::assertTrue($attempted);
            $before = $this->state();
            DB::setDefaultConnection('invitation_writer');
            try {
                $acceptanceFirst ? $activate() : $accept();
                self::fail('The committed membership must remain the winner.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($acceptanceFirst ? 'status' : 'invitation', $exception->errors());
            }
            self::assertSame($before, $this->state());
            self::assertSame(1, AllianceMembership::query()->where('player_id', $fixture['playerId'])->where('status', 'active')->count());
            self::assertSame($acceptanceFirst ? $fixture['userId'] : null, Player::query()->findOrFail($fixture['playerId'])->user_id);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('invitation_writer');
        }
    }

    /** @return iterable<string,array{string,bool}> */
    public static function competingWrites(): iterable
    {
        foreach (['roster departure', 'revocation', 'suspension', 'account deletion'] as $operation) {
            yield 'acceptance before '.$operation => [$operation, true];
            yield $operation.' before acceptance' => [$operation, false];
        }
    }

    #[DataProvider('competingWrites')]
    public function test_acceptance_serializes_with_current_owner_writes_in_both_orders(string $operation, bool $acceptanceFirst): void
    {
        $fixture = $this->fixture();
        $owner = $fixture['owner'];
        $request = $operation === 'account deletion' ? AccountDeletionRequest::query()->create([
            'user_id' => $fixture['userId'], 'status' => 'pending', 'requested_at' => now()->subDay(), 'eligible_at' => now()->subMinute(),
        ]) : null;
        $accept = static fn () => app(AcceptInvitationForAccount::class)->handle($fixture['userId'], $fixture['token']);
        $change = static fn () => match ($operation) {
            'roster departure' => app(MarkRosterEntryLeft::class)->handle($owner->playerId, $fixture['allianceId'], $fixture['rosterId']),
            'revocation' => app(RevokeInvitation::class)->handle($fixture['allianceId'], $owner->playerId, $fixture['invitationId']),
            'suspension' => app(TransitionAllianceLifecycle::class)->handle($owner, $fixture['allianceId'], AllianceStatus::Suspended, 'Administrative suspension'),
            'account deletion' => app(ProcessAccountDeletionRequests::class)->handle(),
        };
        $primary = $this->competitor();
        $attempted = false;
        $locks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $fixture, $operation, $acceptanceFirst, $accept, $change, &$attempted, &$locks): void {
            if ($query->connectionName === $primary && str_starts_with($query->sql, 'select') && (str_contains($query->sql, 'for update') || str_contains($query->sql, 'for share'))) {
                $locks[] = $query->sql;
            }
            $barrier = match ($operation) {
                'roster departure' => str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, 'for update') && in_array($fixture['playerId'], $query->bindings, true),
                'revocation' => str_starts_with($query->sql, 'select * from "invitations"') && str_contains($query->sql, 'for update'),
                'suspension' => str_starts_with($query->sql, 'select * from "alliances"') && str_contains($query->sql, $acceptanceFirst ? 'for share' : 'for update'),
                'account deletion' => str_starts_with($query->sql, 'select * from "users"') && preg_match('/for (?:no key )?update/', $query->sql) === 1 && in_array((string) $fixture['userId'], array_map('strval', $query->bindings), true),
            };
            if ($attempted || $query->connectionName !== $primary || ! $barrier) {
                return;
            }
            $attempted = true;
            if ($acceptanceFirst && $operation === 'roster departure') {
                self::assertTrue(count(array_filter($locks, static fn (string $sql): bool => str_contains($sql, '"alliances"') && str_contains($sql, 'for share'))) > 0);
                self::assertTrue(count(array_filter($locks, static fn (string $sql): bool => str_contains($sql, '"kingdoms"') && str_contains($sql, 'for share'))) > 0);
            }
            DB::setDefaultConnection('invitation_writer');
            try {
                try {
                    $acceptanceFirst ? $change() : $accept();
                    self::fail('The competing owner must wait for the current admission transaction.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $acceptanceFirst ? $accept() : $change();
            self::assertTrue($attempted);
            DB::setDefaultConnection('invitation_writer');
            if ($acceptanceFirst && $operation !== 'revocation') {
                $change();
            } else {
                $before = $this->state();
                DB::transaction(static function () use ($acceptanceFirst, $change, $accept): void {
                    try {
                        $acceptanceFirst ? $change() : $accept();
                        self::fail('Current facts must reject the stale admission or revocation.');
                    } catch (ValidationException|AuthorizationException) {
                        self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
                    }
                });
                self::assertSame($before, $this->state());
            }
            self::assertSame($acceptanceFirst ? InvitationStatus::Accepted : ($operation === 'revocation' ? InvitationStatus::Revoked : InvitationStatus::Pending), Invitation::query()->findOrFail($fixture['invitationId'])->status);
            self::assertSame($acceptanceFirst && $operation !== 'account deletion' ? 1 : 0, AllianceMembership::query()->where('player_id', $fixture['playerId'])->where('status', 'active')->count());
            self::assertSame($acceptanceFirst && $operation !== 'account deletion' ? $fixture['userId'] : null, Player::query()->findOrFail($fixture['playerId'])->user_id);
            if ($request !== null) {
                self::assertSame('processed', $request->fresh()?->status);
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('invitation_writer');
        }
    }

    public function test_revocation_after_scope_discovery_rolls_back_the_whole_player_claim(): void
    {
        $fixture = $this->fixture();
        $primary = $this->competitor();
        $revoked = false;
        $history = DB::table('player_identity_history')->orderBy('id')->get()->toJson();
        DB::listen(static function (QueryExecuted $query) use ($primary, $fixture, &$revoked): void {
            if ($revoked || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $revoked = true;
            DB::setDefaultConnection('invitation_writer');
            try {
                app(RevokeInvitation::class)->handle($fixture['allianceId'], $fixture['owner']->playerId, $fixture['invitationId']);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(AcceptInvitationForAccount::class)->handle($fixture['userId'], $fixture['token']);
                self::fail('Revocation must invalidate the discovered invitation.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('invitation', $exception->errors());
            }
            self::assertTrue($revoked);
            self::assertNull(Player::query()->findOrFail($fixture['playerId'])->user_id);
            self::assertSame($history, DB::table('player_identity_history')->orderBy('id')->get()->toJson());
            self::assertSame(InvitationStatus::Revoked, Invitation::query()->findOrFail($fixture['invitationId'])->status);
            self::assertSame(0, DB::table('audit_events')->where('event', 'player.claimed')->where('subject_id', $fixture['playerId'])->count());
            self::assertSame(0, DB::table('outbox_messages')->where('event_type', 'invitation.accepted')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('invitation_writer');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function invalidCurrentFacts(): iterable
    {
        foreach (['unclaimed', 'foreign owner', 'changed email', 'finalized account', 'archived Kingdom'] as $reason) {
            yield $reason => [$reason];
        }
    }

    #[DataProvider('invalidCurrentFacts')]
    public function test_the_acceptance_owner_validates_current_facts_without_caller_snapshots(string $reason): void
    {
        $fixture = $this->fixture();
        if ($reason !== 'unclaimed') {
            $userId = $reason === 'foreign owner' ? app(ScenarioFactory::class)->account()->userId : $fixture['userId'];
            app(ClaimPlayerAccount::class)->handle($fixture['playerId'], $userId);
        }
        if ($reason === 'changed email') {
            User::query()->whereKey($fixture['userId'])->update(['email' => 'current-address@example.test']);
        } elseif ($reason === 'finalized account') {
            User::query()->whereKey($fixture['userId'])->update(['anonymized_at' => now()]);
        } elseif ($reason === 'archived Kingdom') {
            app(ArchiveKingdom::class)->handle($fixture['owner']->kingdomId);
        }
        $before = $this->state();
        try {
            app(AcceptInvitation::class)->handle($fixture['userId'], $fixture['token'], $fixture['playerId']);
            self::fail('The owner must reload account, ownership and active Kingdom facts.');
        } catch (ValidationException|AuthorizationException) {
            self::assertSame($before, $this->state());
        }
    }

    public function test_a_late_delivery_failure_rolls_back_claim_history_membership_and_invitation(): void
    {
        $fixture = $this->fixture();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected acceptance delivery failure.');
            }
        });
        try {
            app(AcceptInvitationForAccount::class)->handle($fixture['userId'], $fixture['token']);
            self::fail('Admission and account claim must roll back together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected acceptance delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{owner:PlayerReference,userId:int,playerId:string,allianceId:string,rosterId:string,invitationId:string,token:string} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59274);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer(59274);
        $roster = $factory->roster($owner, $alliance, $target);
        $account = $factory->account();
        $issued = app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $account->email);

        return ['owner' => $owner, 'userId' => $account->userId, 'playerId' => $target->playerId, 'allianceId' => $alliance->allianceId, 'rosterId' => $roster->rosterEntryId, 'invitationId' => $issued->invitationId, 'token' => $issued->token];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.invitation_writer', array_replace(DB::connection()->getConfig(), ['name' => 'invitation_writer']));
        DB::connection('invitation_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['players', 'player_identity_history', 'alliance_memberships', 'invitations', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
