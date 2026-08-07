<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Alliances\ValueObjects\TenantContextSnapshot;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ActiveAllianceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_route_requires_an_explicit_active_alliance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/alliance');

        $response->assertStatus(409);
    }

    public function test_alliance_creation_sets_active_context_and_records_correlated_audit(): void
    {
        $user = User::factory()->create();
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $response = $this->actingAs($user)->post('/alliances', [
            'name' => 'Created Through HTTP',
            'slug' => 'created-through-http',
            'kingdom' => '1234',
            'language' => 'en',
            'timezone' => 'America/Toronto',
        ]);

        $alliance = $user->memberships()->with('alliance')->sole()->alliance;

        $response->assertRedirect(route('alliance.overview'));
        $response->assertSessionHas($sessionKey, $alliance->id);

        $audit = AuditEvent::query()->where('event', 'alliance.created')->sole();
        self::assertSame($alliance->id, $audit->alliance_id);
        self::assertNotNull($audit->request_id);
        self::assertNotNull($audit->trace_id);
    }

    public function test_one_global_user_can_switch_between_owned_alliances(): void
    {
        $user = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($user, 'First', 'first');
        $second = $createAlliance->handle($user, 'Second', 'second');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $switch = $this->actingAs($user)
            ->withSession([$sessionKey => $first->id])
            ->put('/alliances/'.$second->id.'/active');

        $switch->assertRedirect(route('alliance.overview'));
        $switch->assertSessionHas($sessionKey, $second->id);

        $overview = $this->get('/alliance');

        $overview->assertOk();
        $overview->assertInertia(fn (Assert $page) => $page
            ->component('Alliance/Overview')
            ->where('alliance.id', $second->id)
            ->where('alliance.name', 'Second'));
    }

    public function test_tenant_middleware_exposes_serializable_context_for_downstream_jobs_and_storage(): void
    {
        Route::middleware(['web', 'auth', 'alliance.context'])
            ->get('/_test/tenant-context', static function (Request $request): JsonResponse {
                $snapshot = $request->attributes->get('tenant_context');

                if (! $snapshot instanceof TenantContextSnapshot) {
                    abort(500, 'Tenant snapshot missing.');
                }

                return response()->json($snapshot->toArray());
            });

        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Snapshot Alliance', 'snapshot-alliance');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/_test/tenant-context');

        $response->assertOk();
        $response->assertJsonPath('alliance_id', $alliance->id);
        $response->assertJsonPath('actor_user_id', $owner->id);
        self::assertIsString($response->json('request_id'));
        self::assertIsString($response->json('trace_id'));
    }

    public function test_user_cannot_activate_an_alliance_without_membership(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Private', 'private');

        $response = $this->actingAs($outsider)
            ->put('/alliances/'.$alliance->id.'/active');

        $response->assertNotFound();
        $response->assertSessionMissing((string) config('identity.active_alliance_session_key'));
    }

    public function test_suspended_membership_clears_stale_active_context(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Suspended', 'suspended');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->update(['status' => MembershipStatus::Suspended->value]);

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance');

        $response->assertForbidden();
        $response->assertSessionMissing($sessionKey);
    }
}
