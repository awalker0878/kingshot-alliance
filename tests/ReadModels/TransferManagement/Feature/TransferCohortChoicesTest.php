<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Feature;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\AssignTransferParticipantCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\ReadModels\TransferManagement\Enums\TransferChoiceKind;
use App\ReadModels\TransferManagement\Queries\TransferManagementChoiceQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\ReadModels\TransferManagement\Support\TransferWorkspaceFixture;
use Tests\TestCase;

final class TransferCohortChoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bounded_cohort_choices_and_assignment_use_the_same_current_compatibility(): void
    {
        $f = TransferWorkspaceFixture::create();
        $target = app(ResolveKingdom::class)->handle(59401);
        $other = app(ResolveKingdom::class)->handle(59402);
        $participant = TransferParticipant::query()->create(['alliance_id' => $f->alliance->allianceId,
            'transfer_plan_id' => $f->plan->id, 'player_id' => $f->actor->playerId, 'observed_name' => 'Choice Governor',
            'direction' => 'outgoing', 'destination_kingdom_id' => $target->kingdomId, 'readiness_state' => 'preparing']);
        $ids = [];
        for ($i = 0; $i < 61; $i++) {
            $cohort = TransferCohort::query()->create(['alliance_id' => $f->alliance->allianceId,
                'transfer_plan_id' => $f->plan->id, 'name' => sprintf('Cohort %03d', $i), 'state' => 'active',
                'direction' => 'outgoing', 'destination_kingdom_id' => $i % 2 === 0 ? null : $target->kingdomId]);
            $ids[] = (string) $cohort->id;
        }
        foreach ([['direction' => 'incoming'], ['state' => 'archived'], ['destination_kingdom_id' => $other->kingdomId]] as $index => $changes) {
            $copy = $cohort->replicate();
            $copy->forceFill(['name' => 'Incompatible cohort '.$index, ...$changes])->save();
        }
        sort($ids);
        $choices = app(TransferManagementChoiceQuery::class);
        $cursor = null;
        $seen = [];
        do {
            $result = $choices->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Cohorts,
                (string) $f->plan->id, cursor: $cursor, selectedId: $ids[60], participantId: (string) $participant->id);
            self::assertSame(61, $result['total']);
            self::assertLessThanOrEqual(25, count($result['page']['items']));
            self::assertSame($ids[60], $result['selected']['id']);
            array_push($seen, ...array_column($result['page']['items'], 'id'));
            $cursor = $result['page']['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($ids, $seen);
        app(AssignTransferParticipantCohort::class)->handle($f->alliance->allianceId, $f->actor->playerId, (string) $f->plan->id, (string) $participant->id, $ids[60]);
        self::assertSame($ids[60], $participant->fresh()->transfer_cohort_id);
        TransferCohort::query()->whereKey($ids[60])->update(['state' => 'archived']);
        $result = $choices->page($f->actor->playerId, $f->alliance->allianceId, TransferChoiceKind::Cohorts,
            (string) $f->plan->id, selectedId: $ids[60], participantId: (string) $participant->id);
        self::assertNull($result['selected']);
        $this->expectException(ValidationException::class);
        app(AssignTransferParticipantCohort::class)->handle($f->alliance->allianceId, $f->actor->playerId, (string) $f->plan->id, (string) $participant->id, $ids[60]);
    }

    public function test_participant_scope_is_required_and_cannot_cross_alliances(): void
    {
        $f = TransferWorkspaceFixture::create();
        $other = TransferWorkspaceFixture::create();
        $participant = TransferParticipant::query()->create(['alliance_id' => $other->alliance->allianceId,
            'transfer_plan_id' => $other->plan->id, 'player_id' => $other->actor->playerId,
            'observed_name' => 'Private Governor', 'direction' => 'incoming', 'readiness_state' => 'preparing']);
        $this->expectException(ModelNotFoundException::class);
        app(TransferManagementChoiceQuery::class)->page($f->actor->playerId, $f->alliance->allianceId,
            TransferChoiceKind::Cohorts, (string) $f->plan->id, participantId: (string) $participant->id);
    }
}
