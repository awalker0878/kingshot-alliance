<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicContentBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_routes_expose_only_published_public_content(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4801]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'public-content-r5',
            'current_name' => 'Public Content R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Public Boundary', 'public-boundary');
        $save = $this->app->make(SaveContentItem::class);
        $publish = $this->app->make(PublishContentItem::class);

        $public = $save->handle($alliance, $ownerPlayer, $this->attributes('Public Notice', 'public-notice'));
        $publish->handle($alliance, $ownerPlayer, $public->id);

        $members = $save->handle(
            $alliance,
            $ownerPlayer,
            $this->attributes('Member Secret', 'member-secret', ContentVisibility::Members),
        );
        $publish->handle($alliance, $ownerPlayer, $members->id);

        $save->handle($alliance, $ownerPlayer, $this->attributes('Draft Notice', 'draft-notice'));

        $this->get('/alliances/public-boundary')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Alliance')
                ->has('content', 1)
                ->where('content.0.slug', 'public-notice'));

        $this->get('/alliances/public-boundary/content/public-notice')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Content')
                ->where('content.title', 'Public Notice'));

        $this->get('/alliances/public-boundary/content/member-secret')->assertNotFound();
        $this->get('/alliances/public-boundary/content/draft-notice')->assertNotFound();

        $this->get('/alliances/public-boundary?q=secret')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('content', 0));
    }

    public function test_member_only_content_requires_the_active_player_to_have_an_active_alliance_membership(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4811]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'member-content-r5',
            'current_name' => 'Member Content R5',
        ]);
        $outsiderPlayer = Player::query()->create([
            'user_id' => $outsider->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'member-content-outsider',
            'current_name' => 'Member Content Outsider',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Member Boundary', 'member-boundary');
        $item = $this->app->make(SaveContentItem::class)->handle(
            $alliance,
            $ownerPlayer,
            $this->attributes('Members Guide', 'members-guide', ContentVisibility::Members),
        );
        $this->app->make(PublishContentItem::class)->handle($alliance, $ownerPlayer, $item->id);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/alliance/content/members-guide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/ContentDetail')
                ->where('content.visibility', 'members'));

        $this->actingAs($outsider)
            ->withSession([$sessionKey => $outsiderPlayer->id])
            ->get('/alliance/content/members-guide')
            ->assertConflict();
    }

    public function test_public_slug_is_scoped_to_the_requested_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 4821]);
        $secondKingdom = Kingdom::query()->create(['number' => 4822]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'public-slug-r5-1',
            'current_name' => 'Public Slug R5 One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'public-slug-r5-2',
            'current_name' => 'Public Slug R5 Two',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First Public', 'first-public');
        $second = $createAlliance->handle($secondPlayer, 'Second Public', 'second-public');
        $save = $this->app->make(SaveContentItem::class);
        $publish = $this->app->make(PublishContentItem::class);

        $firstItem = $save->handle($first, $firstPlayer, $this->attributes('First Shared', 'shared-slug'));
        $secondItem = $save->handle($second, $secondPlayer, $this->attributes('Second Shared', 'shared-slug'));
        $publish->handle($first, $firstPlayer, $firstItem->id);
        $publish->handle($second, $secondPlayer, $secondItem->id);

        $this->get('/alliances/first-public/content/shared-slug')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('content.title', 'First Shared'));
        $this->get('/alliances/second-public/content/shared-slug')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('content.title', 'Second Shared'));
    }

    /** @return array<string, mixed> */
    private function attributes(
        string $title,
        string $slug,
        ContentVisibility $visibility = ContentVisibility::Public,
    ): array {
        return [
            'category_id' => null,
            'type' => ContentType::Announcement,
            'visibility' => $visibility,
            'title' => $title,
            'slug' => $slug,
            'summary' => $title.' summary',
            'body' => $title.' body',
            'locale' => 'en',
            'sort_order' => 0,
        ];
    }
}
