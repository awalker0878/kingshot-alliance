<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Polls\Feature;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\Queries\EventPhaseCatalogueQuery;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollVote;
use App\Contexts\Operations\Polls\Queries\EventPhasePollQuery;
use App\Contexts\Operations\Polls\Queries\EventPollCatalogueQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PollProjectionPagesTest extends TestCase
{
    use RefreshDatabase;

    private PlayerReference $actor;

    private EventOccurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actor = $factory->player((int) $user->id, 62734);
        $alliance = $factory->alliance($this->actor);
        $factory->roster($this->actor, $alliance);
        $scope = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $created = app(CreateEvent::class)->handle($this->actor->playerId, (string) $scope->id,
            EventScope::Alliance, $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        $this->occurrence = EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $this->actor->playerId]);
    }

    public function test_phase_pages_preserve_complete_sort_date_and_null_order_across_ties(): void
    {
        $rows = [];
        for ($i = 0; $i < 81; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $this->occurrence->id,
                'key' => 'phase-'.$i, 'name' => 'Phase '.$i, 'phase_type' => 'custom', 'status' => 'scheduled',
                'sort_order' => $i % 3, 'starts_at' => $i % 4 === 0 ? null : now()->addHours($i % 5)->startOfHour()];
        }
        DB::table('event_phases')->insert($rows);
        $expected = DB::table('event_phases')->orderBy('sort_order')->orderBy('starts_at')->orderBy('id')->pluck('id')->all();
        $query = app(EventPhaseCatalogueQuery::class);
        $page = $query->forOccurrence($this->occurrence, $this->actor->playerId);
        self::assertSame(81, $page['total']);
        $seen = $page['items']->modelKeys();
        EventPhase::query()->create(['occurrence_id' => $this->occurrence->id, 'key' => 'later', 'name' => 'Later insertion',
            'phase_type' => 'custom', 'status' => 'scheduled', 'sort_order' => 10]);
        while ($page['hasMore']) {
            $page = $query->forOccurrence($this->occurrence, $this->actor->playerId, $page['nextCursor']);
            self::assertLessThanOrEqual(25, $page['items']->count());
            $seen = [...$seen, ...$page['items']->modelKeys()];
        }
        self::assertSame($expected, $seen);
        self::assertSame(82, $query->forOccurrence($this->occurrence, $this->actor->playerId)['total']);
    }

    public function test_poll_pages_cover_every_visible_row_and_scope_cursors_to_actor_occurrence_and_visibility(): void
    {
        $ids = $this->polls(62);
        DB::table('event_polls')->where('id', $ids[60])->update(['status' => 'draft']);
        DB::table('event_polls')->where('id', $ids[61])->update(['status' => 'cancelled']);
        $query = app(EventPollCatalogueQuery::class);
        $first = $query->forOccurrence($this->occurrence, $this->actor->playerId, true);
        $cursor = $first['nextCursor'];
        self::assertIsString($cursor);
        self::assertSame(62, $first['total']);
        foreach ([$this->actor->playerId, strtolower((string) Str::ulid())] as $actorId) {
            try {
                $query->forOccurrence($this->occurrence, $actorId, false, $cursor);
                self::fail('Manager and member/other-actor cursor scopes must differ.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('cursor', $exception->errors());
            }
        }
        $otherOccurrence = EventOccurrence::query()->create(['event_id' => $this->occurrence->event_id,
            'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHour(), 'status' => 'scheduled']);
        try {
            $query->forOccurrence($otherOccurrence, $this->actor->playerId, true, $cursor);
            self::fail('A cursor cannot cross occurrence scope.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        $seen = [];
        $cursor = null;
        do {
            $page = $query->forOccurrence($this->occurrence, $this->actor->playerId, false, $cursor);
            self::assertSame(60, $page['total']);
            self::assertLessThanOrEqual(25, $page['items']->count());
            $seen = [...$seen, ...$page['items']->modelKeys()];
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
        self::assertSame(array_slice($ids, 0, 60), $seen);
    }

    public function test_votes_are_exact_without_vote_hydration_and_open_member_results_stay_private(): void
    {
        $ids = $this->polls(55);
        $option = (string) DB::table('event_poll_options')->where('poll_id', $ids[0])->orderBy('sort_order')->value('id');
        $players = $votes = [];
        for ($i = 0; $i < 1500; $i++) {
            $id = strtolower((string) Str::ulid());
            $players[] = ['id' => $id, 'current_kingdom_id' => $this->actor->kingdomId, 'current_name' => 'Retained voter '.$i];
            $votes[] = ['id' => strtolower((string) Str::ulid()), 'poll_id' => $ids[0], 'option_id' => $option, 'player_id' => $id,
                'cast_by_player_id' => $id, 'cast_at' => now()];
        }
        DB::table('players')->insert($players);
        $votes[] = ['id' => strtolower((string) Str::ulid()), 'poll_id' => $ids[0], 'option_id' => $option, 'player_id' => $this->actor->playerId,
            'cast_by_player_id' => $this->actor->playerId, 'cast_at' => now()];
        DB::table('event_poll_votes')->insert($votes);
        $voteRows = $pollRows = 0;
        EventPollVote::retrieved(static function () use (&$voteRows): void {
            $voteRows++;
        });
        EventPoll::retrieved(static function () use (&$pollRows): void {
            $pollRows++;
        });
        $query = app(EventPhasePollQuery::class);
        $manager = $query->forOccurrence($this->occurrence, $this->actor->playerId, manager: true);
        self::assertSame(1501, $manager['polls'][0]['options'][0]['votes']);
        self::assertSame(0, $manager['polls'][0]['options'][1]['votes']);
        self::assertCount(25, $manager['polls']);
        self::assertSame(55, $manager['pollPage']['total']);
        self::assertSame(0, $voteRows);
        self::assertSame(26, $pollRows);
        $member = $query->forOccurrence($this->occurrence, $this->actor->playerId, $this->actor);
        self::assertNull($member['polls'][0]['options'][0]['votes']);
        self::assertSame([$option], $member['polls'][0]['selectedOptionIds']);
        DB::table('event_polls')->where('id', $ids[0])->update(['status' => 'closed']);
        $closed = $query->forOccurrence($this->occurrence, $this->actor->playerId, $this->actor);
        self::assertSame(1501, $closed['polls'][0]['options'][0]['votes']);
        self::assertFalse($closed['polls'][0]['votingOpen']);
        self::assertSame(0, $voteRows);
    }

    public function test_stored_option_overflow_rejects_explicitly_instead_of_returning_partial_options(): void
    {
        $ids = $this->polls(1);
        $rows = [];
        for ($i = 2; $i < 51; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'poll_id' => $ids[0], 'label' => 'Option '.$i,
                'value' => (string) $i, 'sort_order' => $i];
        }
        DB::table('event_poll_options')->insert($rows);
        $this->expectException(ValidationException::class);
        app(EventPhasePollQuery::class)->forOccurrence($this->occurrence, $this->actor->playerId, manager: true);
    }

    public function test_member_route_pages_recheck_authority_and_keep_draft_polls_out_of_the_payload(): void
    {
        $ids = $this->polls(30);
        DB::table('event_polls')->where('id', $ids[0])->update(['status' => 'draft']);
        $first = app(EventPollCatalogueQuery::class)->forOccurrence($this->occurrence, $this->actor->playerId, false);
        $url = '/events/'.$this->occurrence->id.'?poll_cursor='.urlencode((string) $first['nextCursor']);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Operations/Events/Show')
            ->where('event.operations.pollPage.total', 29)->where('event.operations.pollPage.isFirstPage', false)
            ->has('event.operations.polls', 4));
        DB::table('alliance_memberships')->where('player_id', $this->actor->playerId)->update(['status' => 'left']);
        $this->get($url)->assertForbidden();
    }

    /** @return list<string> */
    private function polls(int $count): array
    {
        $polls = $options = [];
        for ($i = 0; $i < $count; $i++) {
            $id = strtolower((string) Str::ulid());
            $polls[] = ['id' => $id, 'occurrence_id' => $this->occurrence->id, 'key' => 'poll-'.$i, 'question' => 'Poll '.$i,
                'poll_type' => 'choice', 'status' => 'open', 'max_choices' => 1, 'created_by_player_id' => $this->actor->playerId,
                'created_at' => now(), 'updated_at' => now()];
            for ($j = 0; $j < 2; $j++) {
                $options[] = ['id' => strtolower((string) Str::ulid()), 'poll_id' => $id,
                    'label' => 'Option '.$j, 'value' => (string) $j, 'sort_order' => $j];
            }
        }
        DB::table('event_polls')->insert($polls);
        DB::table('event_poll_options')->insert($options);

        return array_column($polls, 'id');
    }
}
