<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Content\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class PublicContentBoundaryV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_public_routes_expose_only_published_public_content(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4580, 'Public Owner', 'Public Boundary V2', 'public-boundary-v2-4580');
        $save = app(SaveContentItem::class);
        $publish = app(PublishContentItem::class);

        $public = $save->handle($scenario['alliance'], $scenario['player'], $this->attributes('Public Notice', 'public-notice-v2'));
        $publish->handle($scenario['alliance'], $scenario['player'], $public->id);

        $members = $save->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('Member Secret', 'member-secret-v2', ContentVisibility::Members),
        );
        $publish->handle($scenario['alliance'], $scenario['player'], $members->id);
        $save->handle($scenario['alliance'], $scenario['player'], $this->attributes('Draft Notice', 'draft-notice-v2'));

        $this->get('/alliances/public-boundary-v2-4580')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Alliance')
                ->has('content', 1)
                ->where('content.0.slug', 'public-notice-v2'));

        $this->get('/alliances/public-boundary-v2-4580/content/public-notice-v2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Content')
                ->where('content.title', 'Public Notice'));

        $this->get('/alliances/public-boundary-v2-4580/content/member-secret-v2')->assertNotFound();
        $this->get('/alliances/public-boundary-v2-4580/content/draft-notice-v2')->assertNotFound();
        $this->get('/alliances/public-boundary-v2-4580?q=secret')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('content', 0));
    }

    public function test_member_only_content_requires_active_player_membership_in_exact_alliance(): void
    {
        $factory = new ScenarioFactory;
        $scenario = $factory->alliance(4581, 'Member Owner', 'Member Boundary V2', 'member-boundary-v2-4581');
        $outsiderUser = User::factory()->create();
        $outsider = $factory->playerFor($outsiderUser, 4581, 'Outsider V2', 'member-outsider-v2-4581')['player'];
        $item = app(SaveContentItem::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $this->attributes('Members Guide', 'members-guide-v2', ContentVisibility::Members),
        );
        app(PublishContentItem::class)->handle($scenario['alliance'], $scenario['player'], $item->id);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($scenario['user'])
            ->withSession([$sessionKey => $scenario['player']->id])
            ->get('/alliance/content/members-guide-v2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/ContentDetail')
                ->where('content.visibility', 'members'));

        $this->actingAs($outsiderUser)
            ->withSession([$sessionKey => $outsider->id])
            ->get('/alliance/content/members-guide-v2')
            ->assertConflict();
    }

    public function test_public_content_slug_is_scoped_to_requested_alliance(): void
    {
        $factory = new ScenarioFactory;
        $first = $factory->alliance(4582, 'First Public Owner', 'First Public V2', 'first-public-v2-4582');
        $second = $factory->alliance(4583, 'Second Public Owner', 'Second Public V2', 'second-public-v2-4583');
        $save = app(SaveContentItem::class);
        $publish = app(PublishContentItem::class);

        $firstItem = $save->handle($first['alliance'], $first['player'], $this->attributes('First Shared', 'shared-slug-v2'));
        $secondItem = $save->handle($second['alliance'], $second['player'], $this->attributes('Second Shared', 'shared-slug-v2'));
        $publish->handle($first['alliance'], $first['player'], $firstItem->id);
        $publish->handle($second['alliance'], $second['player'], $secondItem->id);

        $this->get('/alliances/first-public-v2-4582/content/shared-slug-v2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('content.title', 'First Shared'));
        $this->get('/alliances/second-public-v2-4583/content/shared-slug-v2')
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
