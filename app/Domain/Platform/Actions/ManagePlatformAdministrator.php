<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\PlatformAdministrator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManagePlatformAdministrator
{
    public function __construct(private AuditRecorder $audit) {}

    public function grant(User $target, ?User $actor = null): PlatformAdministrator
    {
        return DB::transaction(function () use ($target, $actor): PlatformAdministrator {
            $grant = PlatformAdministrator::query()->firstOrNew(['user_id' => $target->id]);
            $grant->fill([
                'granted_by_user_id' => $actor?->id,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();

            $this->audit->record('platform.administrator.granted', $actor, $grant, null, [
                'target_user_id' => $target->id,
                'bootstrap' => $actor === null,
            ]);

            return $grant->refresh();
        });
    }

    public function revoke(User $actor, PlatformAdministrator $grant): PlatformAdministrator
    {
        if ($grant->user_id === $actor->id) {
            throw new InvalidArgumentException('Platform administrators cannot revoke their own access.');
        }

        if ($grant->revoked_at !== null) {
            return $grant;
        }

        $grant->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('platform.administrator.revoked', $actor, $grant, null, [
            'target_user_id' => $grant->user_id,
        ]);

        return $grant->refresh();
    }
}
