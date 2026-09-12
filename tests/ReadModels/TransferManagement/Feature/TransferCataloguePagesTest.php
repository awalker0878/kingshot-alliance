<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Feature;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\ReadModels\TransferManagement\Enums\TransferCatalogueKind;
use App\ReadModels\TransferManagement\Queries\TransferGroupKingdomPageQuery;
use App\ReadModels\TransferManagement\Queries\TransferManagementCatalogueQuery;
use App\ReadModels\TransferManagement\Queries\TransferManagementPageQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ReadModels\TransferManagement\Support\TransferCatalogueFixture;
use Tests\ReadModels\TransferManagement\Support\TransferWorkspaceFixture;
use Tests\TestCase;

final class TransferCataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    public static function kinds(): iterable
    {
        foreach (TransferCatalogueKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('kinds')]
    public function test_every_catalogue_remains_reachable_in_bounded_pages(TransferCatalogueKind $kind): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = TransferCatalogueFixture::seed($f, $kind);
        if ($kind === TransferCatalogueKind::Windows) {
            $ids[] = (string) $f->plan->transfer_window_id;
        }
        if ($kind === TransferCatalogueKind::Plans) {
            $ids[] = (string) $f->plan->id;
        }
        sort($ids);
        $query = app(TransferManagementCatalogueQuery::class);
        $cursor = null;
        $seen = [];
        do {
            DB::enableQueryLog();
            $result = $query->page($f->actor->playerId, $f->alliance->allianceId, $kind, (string) $f->plan->id, $cursor);
            $sql = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();
            self::assertLessThanOrEqual(25, count($result['items']));
            self::assertSame(count($ids), $result['total']);
            self::assertLessThanOrEqual(20, count($sql));
            self::assertSame($cursor === null, $result['isFirstPage']);
            foreach ($result['items'] as $item) {
                if ($kind === TransferCatalogueKind::Groups) {
                    self::assertArrayNotHasKey('kingdoms', $item);
                }
            }
            array_push($seen, ...array_column($result['items'], 'id'));
            $cursor = $result['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($ids, $seen);
    }

    public function test_deleted_boundary_and_new_insertions_do_not_restart_a_catalogue(): void
    {
        $f = TransferWorkspaceFixture::create();
        $ids = TransferCatalogueFixture::seed($f, TransferCatalogueKind::Cohorts);
        $query = app(TransferManagementCatalogueQuery::class);
        $first = $query->page($f->actor->playerId, $f->alliance->allianceId, TransferCatalogueKind::Cohorts, (string) $f->plan->id);
        TransferCohort::query()->whereKey($ids[24])->delete();
        $copy = TransferCohort::query()->findOrFail($ids[0])->replicate();
        $copy->name = 'Later cohort beyond frontier';
        $copy->save();
        $seen = [];
        $cursor = $first['nextCursor'];
        do {
            $result = $query->page($f->actor->playerId, $f->alliance->allianceId, TransferCatalogueKind::Cohorts, (string) $f->plan->id, $cursor);
            array_push($seen, ...array_column($result['items'], 'id'));
            $cursor = $result['nextCursor'];
        } while ($cursor !== null);
        self::assertSame(array_slice($ids, 25), $seen);
        $this->expectException(ValidationException::class);
        $query->page($f->actor->playerId, $f->alliance->allianceId, TransferCatalogueKind::Windows, cursor: $first['nextCursor']);
    }

    public function test_archived_plan_can_be_read_without_becoming_mutable_and_groups_page_separately(): void
    {
        $f = TransferWorkspaceFixture::create();
        $groupId = TransferCatalogueFixture::seed($f, TransferCatalogueKind::Groups, 1)[0];
        $group = TransferGroup::query()->findOrFail($groupId);
        $ids = [];
        for ($i = 0; $i < 61; $i++) {
            $ids[] = app(ResolveKingdom::class)->handle(62000 + $i)->kingdomId;
        }
        $group->kingdoms()->sync($ids);
        TransferPlan::query()->whereKey($f->plan->id)->update(['state' => 'closed']);
        $payload = app(TransferManagementPageQuery::class)->management($f->actor->playerId, $f->alliance->allianceId, planId: (string) $f->plan->id);
        self::assertNull($payload['mutablePlan']);
        self::assertSame((string) $f->plan->id, $payload['selectedPlan']['id']);
        self::assertSame(61, $payload['catalogues']['officialGroups']['items'][0]['kingdomCount']);
        self::assertArrayNotHasKey('kingdoms', $payload['catalogues']['officialGroups']['items'][0]);
        $cursor = null;
        $seen = [];
        do {
            $page = app(TransferGroupKingdomPageQuery::class)->page($f->actor->playerId, $f->alliance->allianceId, (string) $f->plan->id, $groupId, $cursor);
            self::assertLessThanOrEqual(25, count($page['items']));
            array_push($seen, ...array_column($page['items'], 'id'));
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
        sort($ids);
        self::assertSame($ids, $seen);
    }

    public function test_catalogues_recheck_current_membership_on_continuation(): void
    {
        $f = TransferWorkspaceFixture::create();
        TransferCatalogueFixture::seed($f, TransferCatalogueKind::Cohorts);
        $query = app(TransferManagementCatalogueQuery::class);
        $first = $query->page($f->actor->playerId, $f->alliance->allianceId, TransferCatalogueKind::Cohorts, (string) $f->plan->id);
        DB::table('alliance_memberships')->where('alliance_id', $f->alliance->allianceId)->where('player_id', $f->actor->playerId)->update(['status' => 'left']);
        $this->expectException(AuthorizationException::class);
        $query->page($f->actor->playerId, $f->alliance->allianceId, TransferCatalogueKind::Cohorts, (string) $f->plan->id, $first['nextCursor']);
    }
}
