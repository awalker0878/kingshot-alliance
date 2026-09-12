<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Membership\Integration\Concurrency;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\MarkRosterEntryLeft;
use App\Contexts\Alliance\Membership\Actions\ResendInvitation;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class InvitationIssuanceConcurrencyV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool,bool}> */
    public static function operationsAndOrders(): iterable
    {
        foreach ([false, true] as $resend) {
            yield ($resend ? 'resend' : 'create').' first' => [$resend, true];
            yield ($resend ? 'resend' : 'create').' second' => [$resend, false];
        }
    }

    #[DataProvider('operationsAndOrders')]
    public function test_opposing_alliances_reject_active_targets_without_foreign_membership_locks(bool $resend, bool $firstAlliance): void
    {
        $factory = app(ScenarioFactory::class);
        $firstAccount = $factory->account();
        $secondAccount = $factory->account();
        $firstOwner = $factory->player($firstAccount->userId, 59275);
        $secondOwner = $factory->player($secondAccount->userId, 59275);
        $first = $factory->alliance($firstOwner);
        $second = $factory->alliance($secondOwner);
        $factory->roster($firstOwner, $first, $secondOwner);
        $factory->roster($secondOwner, $second, $firstOwner);
        $firstInvitation = $this->historicalInvitation($first, $firstOwner, $secondOwner, $secondAccount->email);
        $secondInvitation = $this->historicalInvitation($second, $secondOwner, $firstOwner, $firstAccount->email);
        $firstWrite = static fn () => $resend
            ? app(ResendInvitation::class)->handle($first->allianceId, $firstOwner->playerId, $firstInvitation)
            : app(CreateInvitation::class)->handle($first->allianceId, $firstOwner->playerId, $secondOwner->playerId, $secondAccount->email);
        $secondWrite = static fn () => $resend
            ? app(ResendInvitation::class)->handle($second->allianceId, $secondOwner->playerId, $secondInvitation)
            : app(CreateInvitation::class)->handle($second->allianceId, $secondOwner->playerId, $firstOwner->playerId, $firstAccount->email);
        $primary = $this->competitor();
        $attempted = false;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $firstAlliance, $resend, $firstWrite, $secondWrite, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliance_memberships"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('issuance_writer');
            try {
                try {
                    $firstAlliance ? $secondWrite() : $firstWrite();
                    self::fail('An active foreign member is ineligible.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey($resend ? 'invitation' : 'player_id', $exception->errors());
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                $firstAlliance ? $firstWrite() : $secondWrite();
                self::fail('Both opposing invitations must be rejected without waiting.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($resend ? 'invitation' : 'player_id', $exception->errors());
            }
            self::assertTrue($attempted);
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('issuance_writer');
        }
    }

    #[DataProvider('operationsAndOrders')]
    public function test_issuance_and_new_ownership_serialize_and_recheck_the_current_recipient(bool $resend, bool $issuanceFirst): void
    {
        [$owner, $alliance, $target, $issued, $email] = $this->fixture();
        $newOwner = app(ScenarioFactory::class)->account();
        $issue = static fn () => $resend
            ? app(ResendInvitation::class)->handle($alliance->allianceId, $owner->playerId, $issued->invitationId)
            : app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $email);
        $claim = static fn () => app(ClaimPlayerAccount::class)->handle($target->playerId, $newOwner->userId);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $issuanceFirst, $issue, $claim, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"')
                || ! str_contains($query->sql, $issuanceFirst ? 'for share' : 'for update') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('issuance_writer');
            try {
                try {
                    $issuanceFirst ? $claim() : $issue();
                    self::fail('Current ownership must remain stable through eligibility and issuance.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $issuanceFirst ? $issue() : $claim();
            self::assertTrue($attempted);
            DB::setDefaultConnection('issuance_writer');
            if ($issuanceFirst) {
                $claim();
            } else {
                $before = $this->state();
                try {
                    $issue();
                    self::fail('A changed owner must invalidate the old recipient.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey($resend ? 'invitation' : 'email', $exception->errors());
                }
                self::assertSame($before, $this->state());
            }
            self::assertSame($newOwner->userId, Player::query()->findOrFail($target->playerId)->user_id);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('issuance_writer');
        }
    }

    public function test_renewal_and_supersession_preserve_current_capacity_and_invalidate_old_tokens(): void
    {
        [$owner, $alliance, $target, $issued, $email] = $this->fixture();
        DB::table('platform_plan_entitlements')->where('plan_code', 'standard')->where('entitlement_key', 'members.max')->update(['limit_value' => 2]);
        $renewed = app(ResendInvitation::class)->handle($alliance->allianceId, $owner->playerId, $issued->invitationId);
        self::assertSame($issued->invitationId, $renewed->invitationId);
        self::assertNull(app(FindPendingInvitation::class)->byToken($issued->token));
        self::assertNotNull(app(FindPendingInvitation::class)->byToken($renewed->token));
        $replacement = app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $email);
        self::assertSame(InvitationStatus::Revoked, Invitation::query()->findOrFail($renewed->invitationId)->status);
        self::assertNull(app(FindPendingInvitation::class)->byToken($renewed->token));
        self::assertNotNull(app(FindPendingInvitation::class)->byToken($replacement->token));
        self::assertSame(1, Invitation::query()->where('status', 'pending')->count());
        Invitation::query()->whereKey($replacement->invitationId)->update(['expires_at' => now()->subMinute()]);
        DB::table('platform_plan_entitlements')->where('plan_code', 'standard')->where('entitlement_key', 'members.max')->update(['limit_value' => 1]);
        $before = $this->state();
        try {
            app(ResendInvitation::class)->handle($alliance->allianceId, $owner->playerId, $replacement->invitationId);
            self::fail('An expired reservation must reacquire current plan capacity.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('quota', $exception->errors());
        }
        self::assertSame($before, $this->state());
    }

    /** @return iterable<string,array{bool}> */
    public static function operations(): iterable
    {
        yield 'create replacement' => [false];
        yield 'renew token' => [true];
    }

    #[DataProvider('operations')]
    public function test_reconciled_identity_cannot_receive_or_renew_an_invitation(bool $resend): void
    {
        [$owner, $alliance, $target, $issued, $email] = $this->fixture();
        $entry = AllianceRosterEntry::query()->where('alliance_id', $alliance->allianceId)->where('player_id', $target->playerId)->firstOrFail();
        app(MarkRosterEntryLeft::class)->handle($owner->playerId, $alliance->allianceId, (string) $entry->id);
        $canonical = app(PersistPlayerIdentity::class)->handle($target->kingdomId, 'Canonical Governor', null);
        app(ReconcilePlayers::class)->handle($canonical->playerId, $target->playerId, 'Confirmed duplicate identity');
        $before = $this->state();
        try {
            $resend
                ? app(ResendInvitation::class)->handle($alliance->allianceId, $owner->playerId, $issued->invitationId)
                : app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $email);
            self::fail('Invitation eligibility must use the current canonical identity.');
        } catch (ValidationException $exception) {
            self::assertSame(['The invited Player is no longer a current game identity.'], $exception->errors()[$resend ? 'invitation' : 'player_id']);
        }
        self::assertSame($before, $this->state());
    }

    #[DataProvider('operations')]
    public function test_a_late_delivery_failure_rolls_back_supersession_or_token_rotation(bool $resend): void
    {
        [$owner, $alliance, $target, $issued, $email] = $this->fixture();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected issuance delivery failure.');
            }
        });
        try {
            $resend
                ? app(ResendInvitation::class)->handle($alliance->allianceId, $owner->playerId, $issued->invitationId)
                : app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $email);
            self::fail('Invitation and event effects must roll back together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected issuance delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        self::assertNotNull(app(FindPendingInvitation::class)->byToken($issued->token));
    }

    /** @return array{PlayerReference,AllianceReference,PlayerReference,IssuedInvitation,string} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59275);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer(59275);
        $factory->roster($owner, $alliance, $target);
        $email = 'issuance-recipient@example.test';
        $issued = app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $email);

        return [$owner, $alliance, $target, $issued, $email];
    }

    private function historicalInvitation(AllianceReference $alliance, PlayerReference $owner, PlayerReference $target, string $email): string
    {
        return (string) Invitation::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $target->playerId, 'email' => $email,
            'token_hash' => app(InvitationTokenService::class)->hash(Str::random(48)),
            'status' => InvitationStatus::Pending, 'invited_by_player_id' => $owner->playerId, 'expires_at' => now()->addDay(),
        ])->id;
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.issuance_writer', array_replace(DB::connection()->getConfig(), ['name' => 'issuance_writer']));
        DB::connection('issuance_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        return [
            'invitations' => DB::table('invitations')->orderBy('id')->get()->toJson(),
            'audit' => DB::table('audit_events')->orderBy('id')->get()->toJson(),
            'outbox' => DB::table('outbox_messages')->orderBy('id')->get()->toJson(),
        ];
    }
}
