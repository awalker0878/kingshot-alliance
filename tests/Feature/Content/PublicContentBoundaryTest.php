<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Application\Content\PublishContentItem;
use App\Application\Content\SaveContentItem;
use App\Application\Identity\CreateAlliance;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicContentBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_routes_expose_only_published_public_content(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Public Boundary', 'public-boundary');
        $save = $this->app->make(SaveContentItem::class);
        $publish = $this->app->make(PublishContentItem::class);

        $public = $save->handle($alliance, $owner, $this->attributes('Public Notice', 'public-notice'));
        $publish->handle($alliance, $owner, $public->id);

        $members = $save->handle(
            $alliance,
            $owner,
            $this->attributes('Member Secret', 'member-secret', ContentVisibility::Members),
        );
        $publish->handle($alliance, $owner, $members->id);

        $save->handle($alliance, $owner, $this->attributes('Draft Notice', 'draft-notice'));

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

    public function test_member_only_content_requires_active_membership_and_explicit_alliance_context(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Member Boundary', 'member-boundary');
        $item = $this->app->make(SaveContentItem::class)->handle(
            $alliance,
            $owner,
            $this->attributes('Members Guide', 'members-guide', ContentVisibility::Members),
        );
        $this->app->make(PublishContentItem::class)->handle($alliance, $owner, $item->id);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/content/members-guide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/ContentDetail')
                ->where('content.visibility', 'members'));

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/content/members-guide')
            ->assertForbidden();
    }

    public function test_public_slug_is_scoped_to_the_requested_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Public', 'first-public');
        $second = $createAlliance->handle($secondOwner, 'Second Public', 'second-public');
        $save = $this->app->make(SaveContentItem::class);
        $publish = $this->app->make(PublishContentItem::class);

        $firstItem = $save->handle($first, $firstOwner, $this->attributes('First Shared', 'shared-slug'));
        $secondItem = $save->handle($second, $secondOwner, $this->attributes('Second Shared', 'shared-slug'));
        $publish->handle($first, $firstOwner, $firstItem->id);
        $publish->handle($second, $secondOwner, $secondItem->id);

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
