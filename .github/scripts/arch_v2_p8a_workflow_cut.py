from __future__ import annotations

import shutil
from pathlib import Path

ROOT = Path('.')


def require_file(path: str) -> Path:
    target = Path(path)
    if not target.is_file():
        raise RuntimeError(f'Missing expected file: {path}')
    return target


def move_file(source: str, destination: str) -> None:
    src = require_file(source)
    dst = Path(destination)
    if dst.exists():
        raise RuntimeError(f'Destination already exists: {destination}')
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.move(str(src), str(dst))


def move_dir(source: str, destination: str) -> None:
    src = Path(source)
    dst = Path(destination)
    if not src.is_dir():
        raise RuntimeError(f'Missing expected directory: {source}')
    if dst.exists():
        raise RuntimeError(f'Destination already exists: {destination}')
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.move(str(src), str(dst))


def replace_exact(path: str, old: str, new: str, count: int = 1) -> None:
    target = require_file(path)
    source = target.read_text(encoding='utf-8')
    hits = source.count(old)
    if hits != count:
        raise RuntimeError(f'{path}: expected {count} occurrence(s), found {hits}: {old!r}')
    target.write_text(source.replace(old, new, count), encoding='utf-8')


def rewrite_tree(directory: Path, replacements: list[tuple[str, str]]) -> None:
    if not directory.exists():
        return
    for path in sorted(directory.rglob('*.php')):
        source = path.read_text(encoding='utf-8')
        updated = source
        for old, new in replacements:
            updated = updated.replace(old, new)
        if updated != source:
            path.write_text(updated, encoding='utf-8')


legacy = Path('app/Domain/Kingdoms')
if not legacy.is_dir():
    raise RuntimeError('P8A expects app/Domain/Kingdoms to be the final legacy runtime root.')

expected_controllers = {
    'ActivatePlayerController.php',
    'KingdomSettingsController.php',
    'TransferCompletionController.php',
    'TransferGroupController.php',
    'TransferParticipantController.php',
    'TransferPlanController.php',
    'TransferReadinessController.php',
}
actual_controllers = {path.name for path in (legacy / 'Http/Controllers').glob('*.php')}
if actual_controllers != expected_controllers:
    raise RuntimeError(
        f'Unexpected Kingdoms controller set. expected={sorted(expected_controllers)} actual={sorted(actual_controllers)}'
    )

for child in legacy.iterdir():
    if child.name not in {'Actions', 'Enums', 'Http', 'Models', 'Queries', 'README.md'}:
        raise RuntimeError(f'Unexpected final Kingdoms runtime child: {child}')

# Transfer is cross-context orchestration state, not Intelligence. Move the complete
# transfer workflow together, preserving its workflow-owned saga state/models.
for owned_dir in ('Actions', 'Enums', 'Models', 'Queries'):
    move_dir(f'app/Domain/Kingdoms/{owned_dir}', f'app/Workflows/KingdomTransfer/{owned_dir}')
move_file('app/Domain/Kingdoms/README.md', 'app/Workflows/KingdomTransfer/README.md')

for controller in sorted(expected_controllers - {'ActivatePlayerController.php', 'KingdomSettingsController.php'}):
    move_file(
        f'app/Domain/Kingdoms/Http/Controllers/{controller}',
        f'app/Workflows/KingdomTransfer/Http/Controllers/{controller}',
    )

# These two leftovers were never Transfer business state.
move_file(
    'app/Domain/Kingdoms/Http/Controllers/ActivatePlayerController.php',
    'app/Workflows/PlayerContext/Http/Controllers/ActivatePlayerController.php',
)
move_file(
    'app/Domain/Kingdoms/Http/Controllers/KingdomSettingsController.php',
    'app/ReadModels/KingdomSettings/Http/Controllers/KingdomSettingsController.php',
)

shutil.rmtree('app/Domain/Kingdoms/Http')
legacy.rmdir()
domain = Path('app/Domain')
if domain.exists():
    if any(domain.iterdir()):
        raise RuntimeError(f'P8A expected no other app/Domain roots, found: {[p.name for p in domain.iterdir()]}')
    domain.rmdir()

# Rewrite the moved Transfer namespaces and all live references. Architecture tests
# retain historical forbidden V1 strings; executable fixtures/contracts are rewritten.
for path in sorted(Path('app/Workflows/KingdomTransfer').rglob('*.php')):
    source = path.read_text(encoding='utf-8')
    source = source.replace('namespace App\\Domain\\Kingdoms\\', 'namespace App\\Workflows\\KingdomTransfer\\')
    source = source.replace('use App\\Domain\\Kingdoms\\', 'use App\\Workflows\\KingdomTransfer\\')
    path.write_text(source, encoding='utf-8')

replace_exact(
    'app/Workflows/PlayerContext/Http/Controllers/ActivatePlayerController.php',
    'namespace App\\Domain\\Kingdoms\\Http\\Controllers;',
    'namespace App\\Workflows\\PlayerContext\\Http\\Controllers;',
)
replace_exact(
    'app/ReadModels/KingdomSettings/Http/Controllers/KingdomSettingsController.php',
    'namespace App\\Domain\\Kingdoms\\Http\\Controllers;',
    'namespace App\\ReadModels\\KingdomSettings\\Http\\Controllers;',
)

runtime_replacements = [
    ('App\\Domain\\Kingdoms\\Actions\\', 'App\\Workflows\\KingdomTransfer\\Actions\\'),
    ('App\\Domain\\Kingdoms\\Enums\\', 'App\\Workflows\\KingdomTransfer\\Enums\\'),
    ('App\\Domain\\Kingdoms\\Models\\', 'App\\Workflows\\KingdomTransfer\\Models\\'),
    ('App\\Domain\\Kingdoms\\Queries\\', 'App\\Workflows\\KingdomTransfer\\Queries\\'),
    ('App\\Domain\\Kingdoms\\Http\\Controllers\\Transfer', 'App\\Workflows\\KingdomTransfer\\Http\\Controllers\\Transfer'),
    ('App\\Domain\\Kingdoms\\Http\\Controllers\\ActivatePlayerController', 'App\\Workflows\\PlayerContext\\Http\\Controllers\\ActivatePlayerController'),
    ('App\\Domain\\Kingdoms\\Http\\Controllers\\KingdomSettingsController', 'App\\ReadModels\\KingdomSettings\\Http\\Controllers\\KingdomSettingsController'),
]
for root in ('app', 'routes', 'bootstrap', 'config', 'database', 'tests/Feature', 'tests/Integration', 'tests/Unit', 'tests/TenantIsolation'):
    rewrite_tree(Path(root), runtime_replacements)

# Give the moved acceptance tests their actual V2 owners instead of retaining a
# misleading Kingdoms test namespace.
transfer_tests = [
    'TransferCompletionTest.php',
    'TransferGroupTest.php',
    'TransferIncrementAcceptanceTest.php',
    'TransferParticipantTest.php',
    'TransferPlanTest.php',
    'TransferReadinessTest.php',
]
for test in transfer_tests:
    source = f'tests/Feature/Kingdoms/{test}'
    destination = f'tests/Feature/Workflows/KingdomTransfer/{test}'
    move_file(source, destination)
    replace_exact(destination, 'namespace Tests\\Feature\\Kingdoms;', 'namespace Tests\\Feature\\Workflows\\KingdomTransfer;')

move_file(
    'tests/Feature/Kingdoms/KingdomSettingsTest.php',
    'tests/Feature/ReadModels/KingdomSettings/KingdomSettingsTest.php',
)
replace_exact(
    'tests/Feature/ReadModels/KingdomSettings/KingdomSettingsTest.php',
    'namespace Tests\\Feature\\Kingdoms;',
    'namespace Tests\\Feature\\ReadModels\\KingdomSettings;',
)

# GameWorld owns durable Player persistence. Higher contexts/workflows may validate
# their own cross-context blockers, but Player create/update/claim happens here.
game_world_actions = Path('app/Contexts/GameWorld/Actions')
game_world_actions.mkdir(parents=True, exist_ok=True)
(game_world_actions / 'PersistPlayerIdentity.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Actions;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersistPlayerIdentity
{
    public function handle(
        string $kingdomId,
        string $observedName,
        ?string $gamePlayerId,
        ?string $expectedPlayerId = null,
    ): Player {
        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }

        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        return DB::transaction(function () use ($kingdomId, $name, $stableId, $expectedPlayerId): Player {
            Kingdom::query()->whereKey($kingdomId)->sharedLock()->firstOrFail();

            if ($expectedPlayerId !== null) {
                $player = Player::query()->whereKey($expectedPlayerId)->lockForUpdate()->firstOrFail();

                if ($stableId !== null) {
                    $owner = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
                    if ($owner instanceof Player && (string) $owner->id !== (string) $player->id) {
                        throw ValidationException::withMessages([
                            'game_player_id' => 'That game Player ID belongs to a different Player.',
                        ]);
                    }
                    if ($player->game_player_id !== null && $player->game_player_id !== $stableId) {
                        throw ValidationException::withMessages([
                            'game_player_id' => 'The selected Player has a different stable game-player identifier.',
                        ]);
                    }
                }

                $attributes = [
                    'current_kingdom_id' => $kingdomId,
                    'current_name' => $name,
                ];
                if ($stableId !== null && $player->game_player_id === null) {
                    $attributes['game_player_id'] = $stableId;
                }

                $player->forceFill($attributes)->save();

                return $player->refresh();
            }

            if ($stableId !== null) {
                $player = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
                if ($player instanceof Player) {
                    $player->forceFill([
                        'current_kingdom_id' => $kingdomId,
                        'current_name' => $name,
                    ])->save();

                    return $player->refresh();
                }
            }

            return Player::query()->create([
                'current_kingdom_id' => $kingdomId,
                'game_player_id' => $stableId,
                'current_name' => $name,
            ]);
        });
    }
}
''', encoding='utf-8')

(game_world_actions / 'ClaimPlayerAccount.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimPlayerAccount
{
    public function handle(Player $player, User $user): Player
    {
        return DB::transaction(function () use ($player, $user): Player {
            $locked = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== null && (int) $locked->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'player' => 'This Player belongs to another account.',
                ]);
            }

            if ($locked->user_id === null) {
                $locked->forceFill(['user_id' => $user->id])->save();
            }

            return $locked->refresh();
        });
    }
}
''', encoding='utf-8')

# Alliance invitation acceptance may claim a Player, but it no longer writes the
# GameWorld aggregate itself.
invitation = 'app/Contexts/Alliance/Membership/Actions/AcceptInvitation.php'
replace_exact(
    invitation,
    'use App\\Contexts\\GameWorld\\Models\\Player;',
    'use App\\Contexts\\GameWorld\\Actions\\ClaimPlayerAccount;\nuse App\\Contexts\\GameWorld\\Models\\Player;',
)
replace_exact(
    invitation,
    '        private InvitationTokenService $tokens,\n        private AuditRecorder $audit,',
    '        private InvitationTokenService $tokens,\n        private ClaimPlayerAccount $claimPlayerAccount,\n        private AuditRecorder $audit,',
)
replace_exact(
    invitation,
    """            if ($lockedPlayer->user_id === null) {
                $lockedPlayer->forceFill(['user_id' => $currentUser->id])->save();
            }
""",
    """            $lockedPlayer = $this->claimPlayerAccount->handle($lockedPlayer, $currentUser);
""",
)

# Intelligence owns roster rules; GameWorld owns Player persistence.
resolve_roster = Path('app/Contexts/Intelligence/Roster/Actions/ResolvePlayer.php')
resolve_roster.write_text(r'''<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Validation\ValidationException;

final readonly class ResolvePlayer
{
    public function __construct(private PersistPlayerIdentity $playerIdentity) {}

    public function handle(
        Alliance $alliance,
        string $observedName,
        ?string $gamePlayerId,
        ?string $expectedPlayerId = null,
    ): Player {
        if ($alliance->kingdom_id === null) {
            throw ValidationException::withMessages([
                'kingdom' => 'The alliance must have a Kingdom before roster players can be added.',
            ]);
        }

        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }

        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        if ($expectedPlayerId !== null) {
            $expected = Player::query()->lockForUpdate()->findOrFail($expectedPlayerId);

            if ($stableId !== null) {
                $stableOwner = Player::query()
                    ->where('game_player_id', $stableId)
                    ->lockForUpdate()
                    ->first();

                if ($stableOwner instanceof Player && $stableOwner->id !== $expected->id) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'That game Player ID belongs to a different Player.',
                    ]);
                }

                if ($expected->game_player_id !== null && $expected->game_player_id !== $stableId) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'The expected Player has a different stable game-player identifier.',
                    ]);
                }
            }

            $this->assertKingdomCanBeResolved($expected, $alliance);

            return $this->playerIdentity->handle(
                (string) $alliance->kingdom_id,
                $name,
                $stableId,
                (string) $expected->id,
            );
        }

        if ($stableId !== null) {
            $existing = Player::query()
                ->where('game_player_id', $stableId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Player) {
                $this->assertKingdomCanBeResolved($existing, $alliance);

                return $this->playerIdentity->handle(
                    (string) $alliance->kingdom_id,
                    $name,
                    $stableId,
                    (string) $existing->id,
                );
            }
        }

        return $this->playerIdentity->handle((string) $alliance->kingdom_id, $name, $stableId);
    }

    private function assertKingdomCanBeResolved(Player $player, Alliance $alliance): void
    {
        if ((string) $player->current_kingdom_id === (string) $alliance->kingdom_id) {
            return;
        }

        if (KingdomRoleAssignment::query()
            ->where('player_id', $player->id)
            ->where('kingdom_id', $player->current_kingdom_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player still has Kingdom roles in the current Kingdom. Remove or transfer those roles before changing Kingdoms.',
            ]);
        }

        if (AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player has an active Alliance membership. End or transfer the membership before changing Kingdoms.',
            ]);
        }

        if (AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->whereHas('alliance', fn ($query) => $query->where('kingdom_id', '!=', $alliance->kingdom_id))
            ->exists()) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing Kingdoms.',
            ]);
        }
    }
}
''', encoding='utf-8')

# Transfer identity resolution keeps cross-context blocker checks in the workflow,
# but delegates the actual Player create/update to GameWorld.
transfer_resolver = Path('app/Workflows/KingdomTransfer/Actions/ResolveTransferPlayer.php')
transfer_resolver.write_text(r'''<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Actions;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Validation\ValidationException;

final readonly class ResolveTransferPlayer
{
    public function __construct(private PersistPlayerIdentity $playerIdentity) {}

    public function handle(
        Kingdom $sourceKingdom,
        string $name,
        ?string $gamePlayerId,
        ?string $currentPlayerId = null,
    ): Player {
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        $current = $currentPlayerId === null
            ? null
            : Player::query()->lockForUpdate()->findOrFail($currentPlayerId);

        $player = null;
        if ($stableId !== null) {
            $player = Player::query()
                ->where('game_player_id', $stableId)
                ->lockForUpdate()
                ->first();

            if ($current instanceof Player && $player instanceof Player && $player->id !== $current->id) {
                throw ValidationException::withMessages([
                    'game_player_id' => 'That game Player ID belongs to a different Player. Withdraw and recreate the participant to change identity.',
                ]);
            }

            if ($current instanceof Player && ! $player instanceof Player) {
                if ($current->game_player_id !== null && $current->game_player_id !== $stableId) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'Withdraw and recreate the participant to change the Player identity.',
                    ]);
                }

                $player = $current;
            }
        } elseif ($current instanceof Player) {
            $player = $current;
        }

        if ($player instanceof Player) {
            $this->assertKingdomCanBeObserved($player, $sourceKingdom);
        }

        return $this->playerIdentity->handle(
            (string) $sourceKingdom->id,
            trim($name),
            $stableId,
            $player instanceof Player ? (string) $player->id : null,
        );
    }

    private function assertKingdomCanBeObserved(Player $player, Kingdom $sourceKingdom): void
    {
        if ((string) $player->current_kingdom_id === (string) $sourceKingdom->id) {
            return;
        }

        if (KingdomRoleAssignment::query()
            ->where('player_id', $player->id)
            ->where('kingdom_id', $player->current_kingdom_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'That Player still has Kingdom roles in the current Kingdom. Remove or transfer those roles before changing the Player source Kingdom.',
            ]);
        }

        if (AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'That Player has an active Alliance membership. End the membership before changing the Player source Kingdom.',
            ]);
        }

        if (AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->whereHas('alliance', fn ($query) => $query->where('kingdom_id', '!=', $sourceKingdom->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing the Player source Kingdom.',
            ]);
        }
    }
}
''', encoding='utf-8')

completion = 'app/Workflows/KingdomTransfer/Actions/CompleteTransferParticipant.php'
replace_exact(
    completion,
    'use App\\Contexts\\GameWorld\\Governance\\Models\\KingdomRoleAssignment;',
    'use App\\Contexts\\GameWorld\\Actions\\PersistPlayerIdentity;\nuse App\\Contexts\\GameWorld\\Governance\\Models\\KingdomRoleAssignment;',
)
replace_exact(
    completion,
    'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceMutationAuthority;',
    'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceMutationAuthority;\nuse App\\Contexts\\Intelligence\\Roster\\Actions\\MarkRosterEntryLeft;\nuse App\\Contexts\\Intelligence\\Roster\\Actions\\SaveRosterEntry;',
)
replace_exact(
    completion,
    '        private LeaveAlliance $leaveAlliance,\n        private AuditRecorder $audit,',
    '        private LeaveAlliance $leaveAlliance,\n        private PersistPlayerIdentity $playerIdentity,\n        private AuditRecorder $audit,',
)
replace_exact(
    completion,
    """            if ($participant->direction === TransferDirection::Incoming) {
                $participantPlayer->forceFill([
                    'current_kingdom_id' => (string) $plan->home_kingdom_id,
                    'current_name' => (string) $participant->observed_name,
                ])->save();
            }
""",
    """            if ($participant->direction === TransferDirection::Incoming) {
                $participantPlayer = $this->playerIdentity->handle(
                    (string) $plan->home_kingdom_id,
                    (string) $participant->observed_name,
                    $participantPlayer->game_player_id,
                    (string) $participantPlayer->id,
                );
            }
""",
)
replace_exact(
    completion,
    """                $player->forceFill([
                    'current_kingdom_id' => $participant->destination_kingdom_id,
                    'current_name' => (string) $rosterEntry->observed_name,
                ])->save();
""",
    """                $player = $this->playerIdentity->handle(
                    (string) $participant->destination_kingdom_id,
                    (string) $rosterEntry->observed_name,
                    $player->game_player_id,
                    (string) $player->id,
                );
""",
)

# Living workflow/read-model documentation replaces the old Kingdoms catch-all.
workflow_readme = Path('app/Workflows/KingdomTransfer/README.md')
workflow_readme.write_text(
    '# Kingdom Transfer Workflow\n\n'
    'Cross-context transfer-cycle orchestration and saga state. The workflow may read '
    'context state and invoke context-owned mutation APIs, but it does not directly write '
    'GameWorld, Alliance, Operations, or Intelligence aggregates.\n',
    encoding='utf-8',
)
Path('app/Workflows/PlayerContext/README.md').write_text(
    '# Player Context Workflow\n\nCoordinates authenticated Account identity with the selected active GameWorld Player.\n',
    encoding='utf-8',
)
Path('app/ReadModels/KingdomSettings/README.md').write_text(
    '# Kingdom Settings Read Model\n\nCross-context composition for the Kingdom settings experience. It is read-only and owns no business persistence.\n',
    encoding='utf-8',
)

# P8A architecture contract: the V1 runtime tree is gone and the known Player write
# seams must use GameWorld-owned public mutation actions.
architecture = Path('tests/Architecture/ArchitectureV2WorkflowTest.php')
architecture.write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureV2WorkflowTest extends TestCase
{
    public function test_p8_removes_the_final_v1_runtime_root(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Domain');
        self::assertDirectoryExists($this->root().'/app/Workflows/KingdomTransfer');
        self::assertDirectoryExists($this->root().'/app/Workflows/PlayerContext');
        self::assertDirectoryExists($this->root().'/app/ReadModels/KingdomSettings');
    }

    public function test_transfer_workflow_does_not_directly_persist_game_world_players(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Workflows/KingdomTransfer') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('Player::query()->create(', $source, $file);
            self::assertDoesNotMatchRegularExpression('/\$[A-Za-z_][A-Za-z0-9_]*->forceFill\([^;]*current_kingdom_id/s', $source, $file);
        }
    }

    public function test_roster_resolution_delegates_player_persistence_to_game_world(): void
    {
        $source = file_get_contents($this->root().'/app/Contexts/Intelligence/Roster/Actions/ResolvePlayer.php');
        self::assertIsString($source);
        self::assertStringContainsString('PersistPlayerIdentity', $source);
        self::assertStringNotContainsString('Player::query()->create(', $source);
        self::assertStringNotContainsString('->forceFill(', $source);
    }

    public function test_alliance_invitation_claims_player_through_game_world_api(): void
    {
        $source = file_get_contents($this->root().'/app/Contexts/Alliance/Membership/Actions/AcceptInvitation.php');
        self::assertIsString($source);
        self::assertStringContainsString('ClaimPlayerAccount', $source);
        self::assertStringNotContainsString("$lockedPlayer->forceFill(['user_id'", $source);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
''', encoding='utf-8')

# The active runtime and executable fixtures may not retain any V1 namespace.
for root in ('app', 'routes', 'bootstrap', 'config', 'database', 'tests/Feature', 'tests/Integration', 'tests/Unit', 'tests/TenantIsolation'):
    directory = Path(root)
    if not directory.exists():
        continue
    for path in directory.rglob('*.php'):
        source = path.read_text(encoding='utf-8')
        if 'App\\Domain\\' in source:
            raise RuntimeError(f'P8A left a V1 runtime reference in {path}')

print('Applied P8A workflow/read-model hard cut and GameWorld Player persistence boundaries.')
