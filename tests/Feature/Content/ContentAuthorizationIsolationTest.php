<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Content\Actions\PublishContentItem;
use App\Domain\Content\Actions\SaveContentCategory;
use App\Domain\Content\Actions\SaveContentItem;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Actions\CreateInvitation;
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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Authoring Boundary', 'authoring-boundary');
        $invitation = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $invitation->token);

        try {
            $this->app->make(SaveContentItem::class)
                ->handle($alliance, $member, $this->attributes('Blocked', 'blocked'));
            self::fail('An ordinary member must not author content.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $this->actingAs($member)
            ->withSession([(string) config('identity.active_alliance_session_key') => $alliance->id])
            ->get('/alliance/content/manage')
            ->assertForbidden();
    }

    public function test_cross_alliance_category_and_content_identifiers_fail_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Tenant', 'content-first');
        $second = $createAlliance->handle($secondOwner, 'Second Tenant', 'content-second');
        $secondCategory = $this->app->make(SaveContentCategory::class)
            ->handle($second, $secondOwner, 'Private Category', 'private-category');

        try {
            $attributes = $this->attributes('Wrong Category', 'wrong-category');
            $attributes['category_id'] = $secondCategory->id;
            $this->app->make(SaveContentItem::class)->handle($first, $firstOwner, $attributes);
            self::fail('A category from another alliance must not be accepted.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $firstItem = $this->app->make(SaveContentItem::class)
            ->handle($first, $firstOwner, $this->attributes('First Item', 'first-item'));

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(PublishContentItem::class)
            ->handle($second, $secondOwner, $firstItem->id);
    }

    public function test_content_mutations_require_recent_password_confirmation_over_http(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Confirm Boundary', 'confirm-boundary');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/content/manage')
            ->assertOk();

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
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
