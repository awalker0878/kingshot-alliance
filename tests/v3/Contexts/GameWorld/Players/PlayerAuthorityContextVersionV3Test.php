<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use PHPUnit\Framework\TestCase;

final class PlayerAuthorityContextVersionV3Test extends TestCase
{
    public function test_version_is_deterministic_for_equivalent_authority_facts(): void
    {
        $issuer = new PlayerAuthorityContextVersion;
        $player = $this->player();
        $alliance = $this->allianceContext();
        $reordered = $alliance;
        $reordered['roles'] = array_reverse($reordered['roles']);
        $reordered['capabilities'] = array_reverse($reordered['capabilities']);

        self::assertSame(
            $issuer->issue($player, $alliance, ['kingdom.perk.manage', 'kingdom.transfer.manage']),
            $issuer->issue($player, $reordered, ['kingdom.transfer.manage', 'kingdom.perk.manage']),
        );
    }

    public function test_version_changes_when_authority_context_changes(): void
    {
        $issuer = new PlayerAuthorityContextVersion;
        $player = $this->player();
        $alliance = $this->allianceContext();
        $baseline = $issuer->issue($player, $alliance, ['kingdom.perk.manage']);

        $rankChanged = $alliance;
        $rankChanged['rank'] = 'r4';

        $roleChanged = $alliance;
        $roleChanged['roles'] = [['key' => 'diplomat', 'name' => 'Diplomat']];

        $capabilityChanged = $alliance;
        $capabilityChanged['capabilities'] = ['alliance.view'];

        $membershipChanged = $alliance;
        $membershipChanged['membershipId'] = '01JMEMBERSHIP00000000000002';

        $kingdomChanged = new PlayerReference(
            playerId: $player->playerId,
            userId: $player->userId,
            kingdomId: '01JKINGDOM00000000000000002',
            currentName: $player->currentName,
            gamePlayerId: $player->gamePlayerId,
            kingdomNumber: 202,
        );

        foreach ([
            $issuer->issue($player, $rankChanged, ['kingdom.perk.manage']),
            $issuer->issue($player, $roleChanged, ['kingdom.perk.manage']),
            $issuer->issue($player, $capabilityChanged, ['kingdom.perk.manage']),
            $issuer->issue($player, $membershipChanged, ['kingdom.perk.manage']),
            $issuer->issue($player, $alliance, ['kingdom.transfer.manage']),
            $issuer->issue($kingdomChanged, $alliance, ['kingdom.perk.manage']),
        ] as $changed) {
            self::assertNotSame($baseline, $changed);
        }
    }

    private function player(): PlayerReference
    {
        return new PlayerReference(
            playerId: '01JPLAYER000000000000000001',
            userId: 42,
            kingdomId: '01JKINGDOM00000000000000001',
            currentName: 'Governor One',
            gamePlayerId: '123456789',
            kingdomNumber: 101,
        );
    }

    /**
     * @return array{
     *     membershipId:string,
     *     allianceId:string,
     *     allianceName:string,
     *     rank:string,
     *     roles:list<array{key:string,name:string}>,
     *     capabilities:list<string>
     * }
     */
    private function allianceContext(): array
    {
        return [
            'membershipId' => '01JMEMBERSHIP00000000000001',
            'allianceId' => '01JALLIANCE000000000000001',
            'allianceName' => 'Test Alliance',
            'rank' => 'r5',
            'roles' => [
                ['key' => 'diplomat', 'name' => 'Diplomat'],
                ['key' => 'strategist', 'name' => 'Strategist'],
            ],
            'capabilities' => ['alliance.view', 'recruitment.manage'],
        ];
    }
}
