<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomRoleController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        KingdomAuthorization $authorization,
    ): Response {
        $user = $this->user($request);
        $actor = $context->player();
        [$alliance, $kingdom] = $this->context($context);

        if (! $authorization->allows($actor, $kingdom, KingdomPermission::RoleManage)) {
            throw new AuthorizationException;
        }

        $assignments = KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->with(['player:id,current_name,game_player_id', 'role:id,key,name'])
            ->orderBy('created_at')
            ->get()
            ->map(static function (KingdomRoleAssignment $assignment): array {
                return [
                    'id' => (string) $assignment->id,
                    'player' => [
                        'id' => (string) $assignment->player_id,
                        'name' => (string) $assignment->player->current_name,
                        'gamePlayerId' => $assignment->player->game_player_id,
                    ],
                    'role' => [
                        'key' => (string) $assignment->role->key,
                        'name' => (string) $assignment->role->name,
                    ],
                    'assignedAt' => $assignment->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $players = Player::query()
            ->where('current_kingdom_id', $kingdom->id)
            ->orderBy('current_name')
            ->get(['id', 'current_name', 'game_player_id'])
            ->map(static fn (Player $player): array => [
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
                'gamePlayerId' => $player->game_player_id,
            ])
            ->all();

        return Inertia::render('Alliance/KingdomRoles', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
            ],
            'kingdom' => [
                'id' => (string) $kingdom->id,
                'number' => (int) $kingdom->number,
            ],
            'roles' => array_map(
                static fn (DefaultKingdomRole $role): array => [
                    'key' => $role->value,
                    'name' => $role->name(),
                ],
                DefaultKingdomRole::cases(),
            ),
            'players' => $players,
            'assignments' => $assignments,
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        AssignKingdomRole $assign,
    ): RedirectResponse {
        $actor = $context->player();
        [, $kingdom] = $this->context($context);

        $validated = $request->validate([
            'player_id' => ['required', 'string', 'size:26', Rule::exists('players', 'id')->where('current_kingdom_id', $kingdom->id)],
            'role' => ['required', Rule::enum(DefaultKingdomRole::class)],
        ]);

        $target = Player::query()->findOrFail((string) $validated['player_id']);

        $assign->handle(
            actor: $actor,
            kingdom: $kingdom,
            target: $target,
            roleTemplate: DefaultKingdomRole::from((string) $validated['role']),
        );

        return back()->with('status', 'kingdom-role-assigned');
    }

    public function destroy(
        AllianceContext $context,
        KingdomRoleAssignment $assignment,
        RemoveKingdomRole $remove,
    ): RedirectResponse {
        $actor = $context->player();
        [, $kingdom] = $this->context($context);

        $remove->handle($actor, $kingdom, $assignment);

        return back()->with('status', 'kingdom-role-removed');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array{Alliance, Kingdom} */
    private function context(AllianceContext $context): array
    {
        $alliance = $context->alliance()->load('kingdom');
        $kingdom = $alliance->kingdom;

        abort_unless($kingdom instanceof Kingdom, 404, 'The active Alliance has no Kingdom association.');

        return [$alliance, $kingdom];
    }
}
