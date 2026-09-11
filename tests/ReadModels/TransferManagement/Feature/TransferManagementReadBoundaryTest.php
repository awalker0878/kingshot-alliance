<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Feature;

use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\ReadModels\TransferManagement\Support\TransferWorkspaceFixture;
use Tests\TestCase;

final class TransferManagementReadBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_does_not_materialize_or_export_unused_cohort_catalogues(): void
    {
        $f = TransferWorkspaceFixture::create();
        for ($i = 0; $i < 65; $i++) {
            TransferCohort::query()->create(['alliance_id' => $f->alliance->allianceId, 'transfer_plan_id' => $f->plan->id,
                'name' => 'Cohort '.$i, 'state' => 'active', 'direction' => 'outgoing', 'manager_notes' => 'Private '.$i]);
        }
        $hydrated = 0;
        TransferCohort::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $response = $this->actingAs($f->user)->withSession(['players.selected_id' => $f->actor->playerId])
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()) ?? '')
            ->get('/alliance/transfers')->assertOk();
        self::assertArrayNotHasKey('cohorts', $response->json('props'));
        self::assertSame(0, $hydrated);
        self::assertSame((string) $f->plan->id, $response->json('props.plan.id'));
        self::assertSame(0, $response->json('props.participantSummary.total'));
    }

    public function test_read_entry_points_belong_to_the_composition_but_writes_retain_their_owner(): void
    {
        foreach (['/alliance/transfers' => 'index', '/alliance/transfers/manage' => 'manage'] as $path => $method) {
            self::assertSame('App\\ReadModels\\TransferManagement\\Http\\Controllers\\TransferManagementPageController@'.$method,
                Route::getRoutes()->match(Request::create($path))->getActionName());
        }
        self::assertSame('App\\Contexts\\GameWorld\\KingdomTransfers\\Http\\Controllers\\TransferPlanController@store',
            Route::getRoutes()->match(Request::create('/alliance/transfers', 'POST'))->getActionName());
    }

    public function test_revocation_prevents_management_projection_before_private_rows_are_loaded(): void
    {
        $f = TransferWorkspaceFixture::create();
        AllianceMembership::query()->where('alliance_id', $f->alliance->allianceId)->where('player_id', $f->actor->playerId)->update(['rank' => 'r1']);
        $hydrated = 0;
        TransferPlan::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $this->actingAs($f->user)->withSession(['players.selected_id' => $f->actor->playerId])
            ->get('/alliance/transfers/manage')->assertForbidden();
        self::assertSame(0, $hydrated);
    }
}
