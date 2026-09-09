<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class RecruitmentCandidateSelectionQuery
{
    public function __construct(private AllianceAuthorization $authorization, private ScopedCursorCodec $cursors) {}

    /** @return array{page:array{items:list<array{id:string,name:string,rank:string|null,claimed:bool|null}>,nextCursor:string|null,hasMore:bool,pageSize:int,isFirstPage:bool},selected:array{id:string,name:string,rank:string|null,claimed:bool|null}|null} */
    public function forCandidate(string $actorPlayerId, string $allianceId, string $candidateId, string $kind, string $search = '', ?string $cursor = null, ?string $selectedId = null): array
    {
        $this->authorization->authorize($actorPlayerId, $allianceId, AlliancePermission::RecruitmentManage);
        $candidate = RecruitmentCandidate::query()->where('alliance_id', $allianceId)->whereNull('anonymized_at')->whereKey($candidateId)->firstOrFail();
        $search = trim($search);
        if (! in_array($kind, ['members', 'roster', 'templates'], true) || mb_strlen($search) > 160
            || ($selectedId !== null && ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $selectedId))) {
            throw ValidationException::withMessages(['q' => 'The recruitment selection request is invalid.']);
        }

        $base = match ($kind) {
            'members' => DB::table('alliance_memberships as membership')
                ->join('players as player', 'player.id', '=', 'membership.player_id')
                ->where('membership.alliance_id', $allianceId)->where('membership.status', MembershipStatus::Active->value)
                ->selectRaw('player.id AS id, player.current_name AS name, membership.rank AS rank, NULL::boolean AS claimed'),
            'roster' => DB::table('alliance_roster_entries as roster')
                ->join('players as player', 'player.id', '=', 'roster.player_id')
                ->where('roster.alliance_id', $allianceId)->where('roster.state', RosterState::Active->value)
                ->whereNotExists(static function (Builder $members) use ($allianceId): void {
                    $members->selectRaw('1')->from('alliance_memberships')->where('alliance_id', $allianceId)
                        ->whereColumn('player_id', 'roster.player_id')->where('status', MembershipStatus::Active->value);
                })->selectRaw('player.id AS id, player.current_name AS name, NULL::text AS rank, (player.user_id IS NOT NULL) AS claimed'),
            default => DB::table('recruitment_decision_templates')->where('alliance_id', $allianceId)
                ->where('is_active', true)->where('decision_stage', $candidate->recruitmentStage()->value)->selectRaw('id, name, NULL::text AS rank, NULL::boolean AS claimed'),
        };
        $choices = DB::query()->fromSub($base, 'recruitment_choices');
        $selected = $selectedId === null ? null : (clone $choices)->where('id', $selectedId)->first();
        if ($search !== '') {
            $choices->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        }
        $scope = 'recruitment-selection|'.$allianceId.'|'.$candidateId.'|'.$kind.'|'.hash('sha256', $search.'|'.($kind === 'templates' ? $candidate->recruitmentStage()->value : ''));
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $name = $position['name'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($name) || mb_strlen($name) > 255 || ! is_string($id) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $id)) {
                throw ValidationException::withMessages(['cursor' => 'The recruitment selection cursor is invalid.']);
            }
            $choices->where(static function (Builder $after) use ($name, $id): void {
                $after->where('name', '>', $name)->orWhere(static function (Builder $tie) use ($name, $id): void {
                    $tie->where('name', $name)->where('id', '>', $id);
                });
            });
        }
        $rows = $choices->orderBy('name')->orderBy('id')->limit(26)->get();
        $items = array_values($rows->take(25)->map(fn (stdClass $row): array => $this->choice($row))->all());
        $last = $items === [] ? null : $items[array_key_last($items)];

        return [
            'page' => (new PageSlice($items, $rows->count() > 25 && $last !== null ? $this->cursors->encode($scope, ['id' => $last['id'], 'name' => $last['name']]) : null, 25, $cursor === null))->toArray(),
            'selected' => $selected === null ? null : $this->choice($selected),
        ];
    }

    /** @return array{id:string,name:string,rank:string|null,claimed:bool|null} */
    private function choice(stdClass $row): array
    {
        return [
            'id' => (string) $row->id, 'name' => (string) $row->name,
            'rank' => $row->rank === null ? null : (string) $row->rank,
            'claimed' => $row->claimed === null ? null : (bool) $row->claimed,
        ];
    }
}
