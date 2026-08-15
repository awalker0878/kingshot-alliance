<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Accounts\Models\User;
use App\Domain\Platform\Models\PlatformAdministrator;
use App\Domain\Platform\Services\PlatformAdministratorBootstrapCoordinator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManagePlatformAdministrator
{
    public function __construct(
        private AuditRecorder $audit,
        private PlatformAdministratorBootstrapCoordinator $bootstrap,
    ) {}

    public function grant(User $target, ?User $actor = null): PlatformAdministrator
    {
        return DB::transaction(function () use ($target, $actor): PlatformAdministrator {
            $currentActor = null;

            if ($actor instanceof User) {
                $actorGrantId = PlatformAdministrator::query()
                    ->where('user_id', $actor->id)
                    ->whereNull('revoked_at')
                    ->value('id');

                if (! is_string($actorGrantId)) {
                    throw new AuthorizationException('Platform administrator access is required.');
                }

                $actorGrant = PlatformAdministrator::query()
                    ->whereKey($actorGrantId)
                    ->where('user_id', $actor->id)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();

                if (! $actorGrant instanceof PlatformAdministrator) {
                    throw new AuthorizationException('Platform administrator access is required.');
                }

                $currentActor = User::query()
                    ->whereKey($actor->id)
                    ->sharedLock()
                    ->firstOrFail();
            } else {
                // No authority row exists for the initial bootstrap. Coordinate this
                // exceptional global invariant inside Platform itself.
                $this->bootstrap->acquire();

                if (PlatformAdministrator::query()->whereNull('revoked_at')->exists()) {
                    throw new InvalidArgumentException('Bootstrap grants are allowed only when no active Platform Administrator exists.');
                }
            }

            $currentTarget = User::query()
                ->whereKey($target->id)
                ->sharedLock()
                ->firstOrFail();

            $grant = PlatformAdministrator::query()
                ->where('user_id', $currentTarget->id)
                ->lockForUpdate()
                ->first();

            if ($grant instanceof PlatformAdministrator && $grant->revoked_at === null) {
                return $grant;
            }

            $grant ??= new PlatformAdministrator(['user_id' => $currentTarget->id]);
            $grant->forceFill([
                'granted_by_user_id' => $currentActor?->id,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();

            $this->audit->record('platform.administrator.granted', $currentActor, $grant, null, [
                'target_user_id' => $currentTarget->id,
                'bootstrap' => $currentActor === null,
            ]);

            return $grant->refresh();
        });
    }

    public function revoke(User $actor, PlatformAdministrator $grant): PlatformAdministrator
    {
        if ((int) $grant->user_id === (int) $actor->id) {
            throw new InvalidArgumentException('Platform administrators cannot revoke their own access.');
        }

        return DB::transaction(function () use ($actor, $grant): PlatformAdministrator {
            // Resolve both grant ids without locks, then lock the authority rows in
            // deterministic id order so two administrators cannot deadlock by revoking
            // one another concurrently.
            $actorGrantId = PlatformAdministrator::query()
                ->where('user_id', $actor->id)
                ->whereNull('revoked_at')
                ->value('id');

            if (! is_string($actorGrantId)) {
                throw new AuthorizationException('Platform administrator access is required.');
            }

            $grantIds = array_values(array_unique([(string) $actorGrantId, (string) $grant->id]));
            sort($grantIds, SORT_STRING);

            $locked = PlatformAdministrator::query()
                ->whereIn('id', $grantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var PlatformAdministrator|null $actorGrant */
            $actorGrant = $locked->get($actorGrantId);
            /** @var PlatformAdministrator|null $targetGrant */
            $targetGrant = $locked->get($grant->id);

            if (! $actorGrant instanceof PlatformAdministrator
                || $actorGrant->revoked_at !== null
                || (int) $actorGrant->user_id !== (int) $actor->id) {
                throw new AuthorizationException('Platform administrator access is required.');
            }

            if (! $targetGrant instanceof PlatformAdministrator) {
                throw new InvalidArgumentException('The Platform Administrator grant no longer exists.');
            }

            if ($targetGrant->revoked_at !== null) {
                return $targetGrant;
            }

            $currentActor = User::query()
                ->whereKey($actor->id)
                ->sharedLock()
                ->firstOrFail();

            $targetGrant->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('platform.administrator.revoked', $currentActor, $targetGrant, null, [
                'target_user_id' => $targetGrant->user_id,
            ]);

            return $targetGrant->refresh();
        });
    }
}
