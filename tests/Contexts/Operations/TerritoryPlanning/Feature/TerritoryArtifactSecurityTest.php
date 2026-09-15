<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryRendition;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCoordinateTableAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryArtifactSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-evidence-backed-2026-09-06-v2';

    public function test_artifact_http_surfaces_are_private_no_store_and_csv_is_nosniff(): void
    {
        [$user, $actor, $planId] = $this->publishedPlan(61701);
        $session = [$this->sessionKey() => $actor->playerId];

        $this->actingAs($user)
            ->withSession($session)
            ->get(route('territory.hive-templates.index', ['plan' => $planId]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store');

        $this->actingAs($user)
            ->withSession($session)
            ->get(route('territory.renditions.index', ['plan' => $planId]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store');

        $csv = $this->actingAs($user)
            ->withSession($session)
            ->get(route('territory.coordinates.export', ['plan' => $planId]));
        $csv->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename="territory-coordinates.csv"');
        self::assertStringStartsWith('key,type,variant_key,x,y,rotation,alliance_key', $csv->getContent());

        $previewCsv = (new TerritoryCoordinateTableAdapter)->encode([[
            'key' => 'preview-banner',
            'type' => 'banner',
            'x' => 130,
            'y' => 130,
            'rotation' => 0,
            'alliance_key' => 'owner',
            'group_key' => null,
            'player_id' => null,
            'external_player_name' => null,
            'label' => null,
            'metadata' => [],
        ]]);
        $this->actingAs($user)
            ->withSession($session)
            ->postJson(route('territory.coordinates.preview', ['plan' => $planId]), ['csv' => $previewCsv])
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertJsonPath('row_count', 1)
            ->assertJsonPath('rows.0.key', 'preview-banner');
    }

    public function test_rendition_boundary_rejects_active_svg_and_cross_scope_reads(): void
    {
        [$user, $actor, $planId, $revisionId] = $this->publishedPlan(61702);
        $session = [$this->sessionKey() => $actor->playerId];
        $activeSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->actingAs($user)
            ->withSession($session)
            ->postJson(route('territory.renditions.store', ['plan' => $planId]), [
                'revision_id' => $revisionId,
                'scope' => 'world',
                'media_type' => 'image/svg+xml',
                'content_base64' => base64_encode($activeSvg),
                'metadata' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
        self::assertSame(0, TerritoryRendition::query()->count());

        $scenario = new ScenarioFactory;
        $outsiderUser = $scenario->authUser();
        $this->verify($outsiderUser);
        $outsider = $scenario->player((int) $outsiderUser->id, 61702);

        $this->actingAs($outsiderUser)
            ->withSession([$this->sessionKey() => $outsider->playerId])
            ->get(route('territory.coordinates.export', ['plan' => $planId]))
            ->assertForbidden();

        $this->actingAs($outsiderUser)
            ->withSession([$this->sessionKey() => $outsider->playerId])
            ->get(route('territory.renditions.index', ['plan' => $planId]))
            ->assertForbidden();
    }

    public function test_rendition_list_withholds_bytes_and_explicit_reopen_is_private_no_store(): void
    {
        [$user, $actor, $planId, $revisionId] = $this->publishedPlan(61703);
        $session = [$this->sessionKey() => $actor->playerId];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';

        $stored = $this->actingAs($user)
            ->withSession($session)
            ->postJson(route('territory.renditions.store', ['plan' => $planId]), [
                'revision_id' => $revisionId,
                'scope' => 'world',
                'media_type' => 'image/svg+xml',
                'content_base64' => base64_encode($svg),
                'metadata' => [],
            ]);
        $stored->assertOk()->assertHeader('Cache-Control', 'private, no-store');
        $renditionId = (string) $stored->json('rendition.id');
        self::assertNotSame('', $renditionId);

        $list = $this->actingAs($user)
            ->withSession($session)
            ->getJson(route('territory.renditions.index', ['plan' => $planId]));
        $list->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertJsonCount(1, 'renditions')
            ->assertJsonMissingPath('renditions.0.content_base64');

        $this->actingAs($user)
            ->withSession($session)
            ->getJson(route('territory.renditions.show', ['plan' => $planId, 'rendition' => $renditionId]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertJsonPath('rendition.id', $renditionId)
            ->assertJsonPath('rendition.content_base64', base64_encode($svg));
    }

    /**
     * @return array{User,\App\Contexts\GameWorld\Players\ValueObjects\PlayerReference,string,string}
     */
    private function publishedPlan(int $kingdomNumber): array
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $this->verify($user);
        $actor = $scenario->player((int) $user->id, $kingdomNumber);
        $alliance = $scenario->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'KM-17 Artifact Security',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            (string) Str::uuid(),
            [[
                'key' => 'owner',
                'alliance_id' => $alliance->allianceId,
                'external_name' => null,
                'external_tag' => null,
                'display_name' => $alliance->name,
                'presentation_color' => '#4da3ff',
                'sort_order' => 0,
                'visible' => true,
                'locked' => false,
            ]],
            [],
            [
                [
                    'key' => 'hq', 'alliance_key' => 'owner', 'group_key' => null,
                    'type' => 'headquarters', 'player_id' => null, 'external_player_name' => null,
                    'label' => 'HQ', 'x' => 100, 'y' => 100, 'rotation' => 0, 'sort_order' => 0,
                    'metadata' => [],
                ],
                [
                    'key' => 'banner', 'alliance_key' => 'owner', 'group_key' => null,
                    'type' => 'banner', 'player_id' => null, 'external_player_name' => null,
                    'label' => 'Banner', 'x' => 108, 'y' => 100, 'rotation' => 0, 'sort_order' => 1,
                    'metadata' => [],
                ],
                [
                    'key' => 'city', 'alliance_key' => 'owner', 'group_key' => null,
                    'type' => 'governor_city', 'player_id' => null, 'external_player_name' => 'External Governor',
                    'label' => 'Governor', 'x' => 104, 'y' => 108, 'rotation' => 0, 'sort_order' => 2,
                    'metadata' => [],
                ],
                [
                    'key' => 'trap', 'alliance_key' => 'owner', 'group_key' => null,
                    'type' => 'bear_trap', 'player_id' => null, 'external_player_name' => null,
                    'label' => 'Bear Trap', 'x' => 116, 'y' => 116, 'rotation' => 0, 'sort_order' => 3,
                    'metadata' => [],
                ],
            ],
            [],
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            (string) $saved->layoutChecksum,
        );
        self::assertNotNull($published->publishedRevisionId);

        return [$user, $actor, $created->planId, (string) $published->publishedRevisionId];
    }

    private function verify(User $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function sessionKey(): string
    {
        return (string) config('game_world.active_player_session_key');
    }
}
