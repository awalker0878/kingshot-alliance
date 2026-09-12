<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Support;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Tests\Support\ScenarioFactory;

/** Minimal current owner and plan, without materializing unrelated membership or history. */
final readonly class TransferWorkspaceFixture
{
    public function __construct(public User $user, public PlayerReference $actor, public AllianceReference $alliance, public TransferPlan $plan) {}

    public static function create(): self
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now()])->save();
        $actor = $factory->player($account->userId, 59301);
        $alliance = $factory->alliance($actor);
        $window = app(SaveTransferWindow::class)->handle($alliance->allianceId, $actor->playerId, [
            'label' => 'Workspace window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
            'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
            'source_reference' => 'Official window', 'observed_at' => now()->subDays(4)->toIso8601String(),
        ]);
        app(CreateTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, ['label' => 'Workspace plan', 'transfer_window_id' => $window]);

        return new self($user, $actor, $alliance, TransferPlan::query()->where('alliance_id', $alliance->allianceId)->sole());
    }
}
