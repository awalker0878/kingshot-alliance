<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateAllianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_creation_is_transactional_and_provisions_owner_authorization(): void
    {
        $owner = User::factory()->create();

        $alliance = $this->app->make(CreateAlliance::class)->handle(
            owner: $owner,
            name: 'Kingshot One',
            slug: 'kingshot-one',
            kingdom: '1234',
            language: 'en',
            timezone: 'America/Toronto',
        );

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();

        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertNotNull($membership->joined_at);
        self::assertSame(7, Role::query()->where('alliance_id', $alliance->id)->count());
        self::assertSame(count(PermissionKey::cases()), Permission::query()->count());
        self::assertTrue($membership->roles()->where('roles.key', 'owner')->exists());

        $authorization = $this->app->make(AllianceAuthorization::class);

        foreach (PermissionKey::cases() as $permission) {
            self::assertTrue($authorization->allows($owner, $alliance, $permission));
        }

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => $owner->id,
            'event' => 'alliance.created',
            'subject_type' => $alliance::class,
            'subject_id' => $alliance->id,
        ]);

        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'alliance.created',
            'aggregate_type' => $alliance::class,
            'aggregate_id' => $alliance->id,
            'idempotency_key' => 'alliance.created:'.$alliance->id,
        ]);

        self::assertSame(1, AuditEvent::query()->count());
        self::assertSame(1, OutboxMessage::query()->count());
    }

    public function test_one_global_user_can_own_multiple_alliances(): void
    {
        $owner = User::factory()->create();
        $action = $this->app->make(CreateAlliance::class);

        $first = $action->handle($owner, 'First Alliance', 'first-alliance');
        $second = $action->handle($owner, 'Second Alliance', 'second-alliance');

        self::assertNotSame($first->id, $second->id);
        self::assertSame(2, AllianceMembership::query()
            ->where('user_id', $owner->id)
            ->where('status', MembershipStatus::Active->value)
            ->count());
    }
}
