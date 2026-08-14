<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Content;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Content\Actions\PublishContentItem;
use App\Domain\Content\Actions\SaveContentCategory;
use App\Domain\Content\Actions\SaveContentItem;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ContentAuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_member_cannot_author_content_or_open_management_console(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4301]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'content-owner',
            'current_name' => 'Content Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'content-member',
            'current_name' => 'Content Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Authoring Boundary', 'authoring-boundary');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        try {
            $this->app->make(SaveContentItem::class)
                ->handle($alliance, $memberPlayer, $this->attributes('Blocked', 'blocked'));
            self::fail('An ordinary member Player must not author content.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $this->actingAs($member)
            ->withSession([(string) config('identity.active_player_session_key') => $memberPlayer->id])
            ->get('/alliance/content/manage')
            ->assertForbidden();
    }

    public function test_cross_alliance_category_and_content_identifiers_fail_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 4311]);
        $secondKingdom = Kingdom::query()->create(['number' => 4312]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'content-tenant-owner-1',
            'current_name' => 'Content Tenant One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'content-tenant-owner-2',
            'current_name' => 'Content Tenant Two',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First Tenant', 'content-first');
        $second = $createAlliance->handle($secondPlayer, 'Second Tenant', 'content-second');
        $secondCategory = $this->app->make(SaveContentCategory::class)
            ->handle($second, $secondPlayer, 'Private Category', 'private-category');

        try {
            $attributes = $this->attributes('Wrong Category', 'wrong-category');
            $attributes['category_id'] = $secondCategory->id;
            $this->app->make(SaveContentItem::class)->handle($first, $firstPlayer, $attributes);
            self::fail('A category from another alliance must not be accepted.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $firstItem = $this->app->make(SaveContentItem::class)
            ->handle($first, $firstPlayer, $this->attributes('First Item', 'first-item'));

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(PublishContentItem::class)
            ->handle($second, $secondPlayer, $firstItem->id);
    }

    public function test_content_mutations_require_recent_password_confirmation_over_http(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4321]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'content-confirm-owner',
            'current_name' => 'Content Confirmation Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Confirm Boundary', 'confirm-boundary');
        $sessionKey = (string) config('identity.active_player_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/alliance/content/manage')
            ->assertOk();

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->post('/alliance/content', [
                'type' => ContentType::Announcement->value,
                'visibility' => ContentVisibility::Public->value,
                'title' => 'Needs Confirmation',
                'slug' => 'needs-confirmation',
                'body' => 'Body',
                'locale' => 'en',
            ]);

        $response->assertRedirect(route('password.confirm'));
        $this->assertDatabaseMissing('content_items', ['slug' => 'needs-confirmation']);
    }

    /** @return array<string, mixed> */
    private function attributes(string $title, string $slug): array
    {
        return [
            'category_id' => null,
            'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Public,
            'title' => $title,
            'slug' => $slug,
            'summary' => null,
            'body' => $title.' body',
            'locale' => 'en',
            'sort_order' => 0,
        ];
    }
}
