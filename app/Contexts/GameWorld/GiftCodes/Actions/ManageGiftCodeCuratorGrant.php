<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeCuratorGrant;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageGiftCodeCuratorGrant
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private AuditRecorder $audit,
        private PlatformAuthorization $platformAuthorization,
    ) {}

    public function grant(AccountIdentity $actor, int $targetUserId): string
    {
        $this->authorizePlatformAdministrator($actor);
        if (! $this->accounts->exists($targetUserId)) {
            throw new InvalidArgumentException('The target account does not exist.');
        }

        return DB::transaction(function () use ($actor, $targetUserId): string {
            $grant = GiftCodeCuratorGrant::query()
                ->where('user_id', $targetUserId)
                ->lockForUpdate()
                ->first();

            if ($grant instanceof GiftCodeCuratorGrant && $grant->revoked_at === null) {
                return (string) $grant->id;
            }

            $grant ??= new GiftCodeCuratorGrant(['user_id' => $targetUserId]);
            $grant->forceFill([
                'granted_by_user_id' => $actor->userId,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();

            $this->audit->record('game_world.gift_code_curator.granted', $actor, $grant, null, [
                'target_user_id' => $targetUserId,
            ]);

            return (string) $grant->id;
        });
    }

    public function revoke(AccountIdentity $actor, string $grantId): string
    {
        $this->authorizePlatformAdministrator($actor);

        return DB::transaction(function () use ($actor, $grantId): string {
            $grant = GiftCodeCuratorGrant::query()->whereKey($grantId)->lockForUpdate()->first();
            if (! $grant instanceof GiftCodeCuratorGrant) {
                throw new InvalidArgumentException('The Gift Code curator grant no longer exists.');
            }

            if ($grant->revoked_at === null) {
                $grant->forceFill(['revoked_at' => now()])->save();
                $this->audit->record('game_world.gift_code_curator.revoked', $actor, $grant, null, [
                    'target_user_id' => $grant->user_id,
                ]);
            }

            return (string) $grant->id;
        });
    }

    private function authorizePlatformAdministrator(AccountIdentity $actor): void
    {
        if (! $actor->emailVerified || ! $actor->multiFactorConfirmed || ! $this->platformAuthorization->allows($actor)) {
            throw new AuthorizationException('MFA-protected platform administrator access is required.');
        }
    }
}
