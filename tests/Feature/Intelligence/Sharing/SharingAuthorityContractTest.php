<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence\Sharing;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Sharing\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Contexts\Intelligence\Sharing\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShare;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SharingAuthorityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharing_authority_is_player_scoped_and_acceptance_records_the_recipient_player(): void
    {
        $sourceUser = User::factory()->create();
        $recipientUser = User::factory()->create();
        $kingdom = $this->kingdom(9401);
        $sourceOwner = $this->player($sourceUser, $kingdom, '9401-source', 'Source Owner');
        $sourceSibling = $this->player($sourceUser, $kingdom, '9401-sibling', 'Source Sibling');
        $recipientOwner = $this->player($recipientUser, $kingdom, '9401-recipient', 'Recipient Owner');
        $source = $this->app->make(CreateAlliance::class)->handle($sourceOwner, 'Sharing Source', 'sharing-source');
        $recipient = $this->app->make(CreateAlliance::class)->handle($recipientOwner, 'Sharing Recipient', 'sharing-recipient');
        $create = $this->app->make(CreateKingdomIntelligenceShareInvitation::class);

        $issued = $create->handle($source, $sourceOwner);
        $accepted = $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, $recipientOwner, $issued->token);

        self::assertSame(KingdomIntelligenceShareState::Active, $accepted->state);
        self::assertSame((string) $source->id, (string) $accepted->source_alliance_id);
        self::assertSame((string) $recipient->id, (string) $accepted->recipient_alliance_id);
        self::assertSame((string) $sourceOwner->id, (string) $accepted->invited_by_player_id);
        self::assertSame((string) $recipientOwner->id, (string) $accepted->accepted_by_player_id);

        $this->expectException(AuthorizationException::class);
        $create->handle($source, $sourceSibling);
    }

    public function test_invitation_cannot_be_accepted_after_recipient_is_in_a_different_kingdom(): void
    {
        $sourceUser = User::factory()->create();
        $recipientUser = User::factory()->create();
        $sourceKingdom = $this->kingdom(9402);
        $otherKingdom = $this->kingdom(9403);
        $sourceOwner = $this->player($sourceUser, $sourceKingdom, '9402-source', 'Source Owner');
        $recipientOwner = $this->player($recipientUser, $otherKingdom, '9403-recipient', 'Recipient Owner');
        $source = $this->app->make(CreateAlliance::class)->handle($sourceOwner, 'Captured Kingdom Source', 'captured-source');
        $recipient = $this->app->make(CreateAlliance::class)->handle($recipientOwner, 'Other Kingdom Recipient', 'other-recipient');
        $issued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)->handle($source, $sourceOwner);

        $this->expectException(ValidationException::class);
        $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, $recipientOwner, $issued->token);

        self::assertSame(
            KingdomIntelligenceShareState::Pending,
            KingdomIntelligenceShare::query()->whereKey($issued->shareId)->sole()->state,
        );
    }

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->create([
            'number' => $number,
            'status' => 'active',
        ]);
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }
}
