<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAllianceKingdom
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ResolveKingdom $kingdoms,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, int|string|null $number): Alliance
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::AllianceManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $number): Alliance {
            $locked = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $previous = $locked->kingdom()->first();
            $kingdom = $this->kingdoms->handle($number);

            if ($locked->kingdom_id === $kingdom?->id) {
                return $locked->load('kingdom');
            }

            $locked->forceFill([
                'kingdom_id' => $kingdom?->id,
            ])->save();

            $metadata = [
                'previous_kingdom_id' => $previous?->id,
                'previous_kingdom_number' => $previous instanceof Kingdom ? $previous->number : null,
                'kingdom_id' => $kingdom?->id,
                'kingdom_number' => $kingdom?->number,
            ];

            $this->audit->record(
                event: 'alliance.kingdom_updated',
                actor: $actor,
                subject: $locked,
                alliance: $locked,
                metadata: $metadata,
            );

            $this->outbox->record(
                eventType: 'alliance.kingdom_updated',
                allianceId: (string) $locked->id,
                aggregate: $locked,
                payload: $metadata,
            );

            return $locked->refresh()->load('kingdom');
        });
    }
}
