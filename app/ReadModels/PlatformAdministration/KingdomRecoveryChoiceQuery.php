<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Services\PlatformAdministratorAuthorization;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/** Operator choices are current read facts; only the later Workflow authorizes recovery. */
final readonly class KingdomRecoveryChoiceQuery
{
    public function __construct(private AccountIdentityQuery $accounts, private PlatformAdministratorAuthorization $authorization,
        private ScopedCursorCodec $cursors) {}

    /** @return array{page:array{items:list<array{id:string,name:string}>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool},total:int,selected:?array{id:string,name:string}} */
    public function page(int $actorId, string $kind, ?string $kingdomId = null, string $search = '', ?string $cursor = null, ?string $selectedId = null): array
    {
        $this->authorization->authorize($this->accounts->require($actorId));
        $search = trim($search);
        if (! in_array($kind, ['kingdoms', 'players'], true) || mb_strlen($search) > 160 || ($selectedId !== null && ! $this->isId($selectedId))) {
            throw ValidationException::withMessages(['choices' => 'The recovery choice request is invalid.']);
        }
        if ($kind === 'players') {
            if (! $this->isId($kingdomId) || ! DB::table('kingdoms')->where('id', $kingdomId)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['kingdom_id' => 'Choose a current active Kingdom.']);
            }
            $base = DB::table('players')->where('current_kingdom_id', $kingdomId)->whereNull('canonical_player_id')
                ->selectRaw("id, current_name || CASE WHEN game_player_id IS NULL THEN '' ELSE ' · ' || game_player_id END as name");
        } else {
            if ($kingdomId !== null) {
                throw ValidationException::withMessages(['kingdom_id' => 'Kingdom choices do not accept a parent Kingdom.']);
            }
            $base = DB::table('kingdoms')->where('status', 'active')->selectRaw("id, '#' || number::text as name");
        }
        $query = DB::query()->fromSub($base, 'recovery_choices');
        $selected = $selectedId === null ? null : (clone $query)->where('id', $selectedId)->first();
        if ($search !== '') {
            $query->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        }
        $scope = implode('|', ['kingdom-recovery-choices', $actorId, $kind, $kingdomId ?? '', hash('sha256', $search)]);
        $total = (clone $query)->count();
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 2 || ! $this->isId($after) || ! $this->isId($through) || strcmp($after, $through) > 0) {
                throw ValidationException::withMessages(['cursor' => 'The recovery choice cursor is invalid.']);
            }
            $query->where('id', '>', $after);
        }
        $rows = $through === null ? collect() : $query->where('id', '<=', $through)->orderBy('id')->limit(26)->get();
        $items = array_values($rows->take(25)->map(fn (stdClass $row): array => $this->choice($row))->all());
        $last = $items === [] ? null : $items[array_key_last($items)]['id'];
        $next = $rows->count() > 25 && $last !== null ? $this->cursors->encode($scope, ['after' => $last, 'through' => $through]) : null;

        return ['page' => (new PageSlice($items, $next, 25, $cursor === null))->toArray(), 'total' => $total,
            'selected' => $selected === null ? null : $this->choice($selected)];
    }

    /** @phpstan-assert-if-true non-empty-string $id */
    private function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $id) === 1;
    }

    /** @return array{id:string,name:string} */
    private function choice(stdClass $row): array
    {
        return ['id' => (string) $row->id, 'name' => (string) $row->name];
    }
}
