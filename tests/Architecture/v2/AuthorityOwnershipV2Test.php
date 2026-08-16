<?php

declare(strict_types=1);

namespace Tests\Architecture\V2;

use PHPUnit\Framework\TestCase;
use Tests\Support\V2\RepositoryInspector;

final class AuthorityOwnershipV2Test extends TestCase
{
    public function test_game_authority_is_player_scoped(): void
    {
        $membership = RepositoryInspector::source(RepositoryInspector::absolute('app/Contexts/Alliance/Membership/Models/AllianceMembership.php'));
        $assignment = RepositoryInspector::source(RepositoryInspector::absolute('app/Contexts/GameWorld/Governance/Models/KingdomRoleAssignment.php'));
        $authorization = RepositoryInspector::source(RepositoryInspector::absolute('app/Contexts/GameWorld/Governance/Services/KingdomAuthorization.php'));

        self::assertStringContainsString("'player_id'", $membership);
        self::assertStringNotContainsString("'user_id'", $membership);
        self::assertStringContainsString("'player_id'", $assignment);
        self::assertStringNotContainsString("'user_id'", $assignment);
        self::assertStringContainsString('Player $player', $authorization);
        self::assertStringNotContainsString('User $user', $authorization);
    }

    public function test_account_and_game_world_models_do_not_navigate_back_into_feature_aggregates(): void
    {
        $user = RepositoryInspector::source(RepositoryInspector::absolute('app/Contexts/Accounts/Models/User.php'));
        $player = RepositoryInspector::source(RepositoryInspector::absolute('app/Contexts/GameWorld/Models/Player.php'));
        $kingdom = RepositoryInspector::source(RepositoryInspector::absolute('app/Contexts/GameWorld/Models/Kingdom.php'));

        foreach (['hasMany(', 'hasOne(', 'belongsToMany(', 'morphMany(', 'morphToMany('] as $relation) {
            self::assertStringNotContainsString($relation, $user);
            self::assertStringNotContainsString($relation, $player);
            self::assertStringNotContainsString($relation, $kingdom);
        }

        foreach (['memberships(', 'roleAssignments(', 'rosterEntries(', 'events(', 'contributions(', 'rallies('] as $method) {
            self::assertStringNotContainsString('function '.$method, $player);
        }
    }

    public function test_downstream_contexts_interpret_alliance_authority_inside_their_own_access_boundary(): void
    {
        foreach (['Operations', 'Intelligence'] as $context) {
            foreach (RepositoryInspector::phpFiles('app/Contexts/'.$context) as $file) {
                $relative = RepositoryInspector::relative($file);
                $source = RepositoryInspector::source($file);
                $usesAllianceAuthority = str_contains($source, 'App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization')
                    || str_contains($source, 'App\\Contexts\\Alliance\\Access\\Services\\AllianceMutationAuthority');

                if (! $usesAllianceAuthority) {
                    continue;
                }

                self::assertStringContainsString(
                    '/Access/Services/',
                    '/'.str_replace('\\', '/', $relative),
                    $relative.' interprets Alliance authority outside the downstream context access boundary.',
                );
            }
        }
    }

    public function test_kingdom_transfer_owns_transfer_authority(): void
    {
        foreach ([
            'app/Workflows/KingdomTransfer/Access/Enums/TransferPermission.php',
            'app/Workflows/KingdomTransfer/Access/Services/TransferAuthorization.php',
            'app/Workflows/KingdomTransfer/Access/Services/TransferMutationAuthority.php',
        ] as $path) {
            self::assertFileExists(RepositoryInspector::absolute($path));
        }

        foreach (RepositoryInspector::phpFiles('app/Workflows/KingdomTransfer') as $file) {
            $source = RepositoryInspector::source($file);
            self::assertStringNotContainsString('App\\Contexts\\Intelligence\\Access\\', $source);
            self::assertStringNotContainsString('IntelligencePermission::KingdomManage', $source);
        }
    }

    public function test_player_persistence_is_owned_by_game_world(): void
    {
        foreach (['app/Contexts/Intelligence/Roster', 'app/Workflows/KingdomTransfer'] as $directory) {
            $combined = '';
            foreach (RepositoryInspector::phpFiles($directory) as $file) {
                $source = RepositoryInspector::source($file);
                $combined .= $source;
                self::assertStringNotContainsString('Player::query()->create(', $source, RepositoryInspector::relative($file).' bypasses GameWorld Player persistence.');
            }
            self::assertStringContainsString('PersistPlayerIdentity', $combined, $directory.' must delegate Player persistence to GameWorld.');
        }

        $membership = '';
        foreach (RepositoryInspector::phpFiles('app/Contexts/Alliance/Membership') as $file) {
            $membership .= RepositoryInspector::source($file);
        }
        self::assertStringContainsString('ClaimPlayerAccount', $membership);
    }
}
