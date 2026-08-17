<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\Administration\Services\PlatformAdministratorBootstrapCoordinator;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManagePlatformAdministrator
{
    public function __construct(
        private AuditRecorder $audit,
        private PlatformAdministratorBootstrapCoordinator $bootstrap,
        private AccountIdentityQuery $accounts,
    ) {}

    public function grant(int $targetUserId, ?AccountIdentity $actor = null): string
    {
        if (! $this->accounts->exists($targetUserId)) {
            throw new InvalidArgumentException('The target account does not exist.');
        }

        return DB::transaction(function () use ($targetUserId, $actor): string {
            if ($actor instanceof AccountIdentity) {
                $actorGrant = PlatformAdministrator::query()
                    ->where('user_id', $actor->userId)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();

                if (! $actorGrant instanceof PlatformAdministrator) {
                    throw new AuthorizationException('Platform administrator access is required.');
                }
            } else {
                $this->bootstrap->acquire();

                if (PlatformAdministrator::query()->whereNull('revoked_at')->exists()) {
                    throw new InvalidArgumentException(
                        'Bootstrap grants are allowed only when no active Platform Administrator exists.',
                    );
                }
            }

            $grant = PlatformAdministrator::query()
                ->where('user_id', $targetUserId)
                ->lockForUpdate()
                ->first();

            if ($grant instanceof PlatformAdministrator && $grant->revoked_at === null) {
                return (string) $grant->id;
            }

            $grant ??= new PlatformAdministrator(['user_id' => $targetUserId]);
            $grant->forceFill([
                'granted_by_user_id' => $actor?->userId,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();

            $this->audit->record(
                'platform.administrator.granted',
                $actor,
                $grant,
                null,
                [
                    'target_user_id' => $targetUserId,
                    'bootstrap' => $actor === null,
                ],
            );

            return (string) $grant->id;
        });
    }

    public function revoke(AccountIdentity $actor, string $grantId): string
    {
        return DB::transaction(function () use ($actor, $grantId): string {
            $actorGrantId = PlatformAdministrator::query()
                ->where('user_id', $actor->userId)
                ->whereNull('revoked_at')
                ->value('id');

            if (! is_string($actorGrantId)) {
                throw new AuthorizationException('Platform administrator access is required.');
            }

            $grantIds = array_values(array_unique([(string) $actorGrantId, $grantId]));
            sort($grantIds, SORT_STRING);

            $locked = PlatformAdministrator::query()
                ->whereIn('id', $grantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $actorGrant = $locked->get($actorGrantId);
            $targetGrant = $locked->get($grantId);

            if (! $actorGrant instanceof PlatformAdministrator
                || $actorGrant->revoked_at !== null
                || (int) $actorGrant->user_id !== $actor->userId) {
                throw new AuthorizationException('Platform administrator access is required.');
            }

            if (! $targetGrant instanceof PlatformAdministrator) {
                throw new InvalidArgumentException('The Platform Administrator grant no longer exists.');
            }

            if ((int) $targetGrant->user_id === $actor->userId) {
                throw new InvalidArgumentException('Platform administrators cannot revoke their own access.');
            }

            if ($targetGrant->revoked_at !== null) {
                return (string) $targetGrant->id;
            }

            $targetGrant->forceFill(['revoked_at' => now()])->save();

            $this->audit->record(
                'platform.administrator.revoked',
                $actor,
                $targetGrant,
                null,
                ['target_user_id' => $targetGrant->user_id],
            );

            return (string) $targetGrant->id;
        });
    }
}
