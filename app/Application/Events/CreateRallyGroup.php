<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\EventOccurrence;
use App\Models\EventRecommendedFormation;
use App\Models\RallyGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRallyGroup
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        EventOccurrence $occurrence,
        string $name,
        ?int $maxJoiners = null,
        ?EventRecommendedFormation $recommendedFormation = null,
        ?string $notes = null,
        int $sortOrder = 0,
    ): RallyGroup {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage rally groups.');
        }

        if ($occurrence->alliance_id !== $alliance->id
            || ($recommendedFormation !== null && $recommendedFormation->alliance_id !== $alliance->id)) {
            throw new AuthorizationException('The rally group references another alliance.');
        }

        if ($maxJoiners !== null && $maxJoiners < 1) {
            throw new InvalidArgumentException('Maximum joiners must be at least one when provided.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $occurrence,
            $name,
            $maxJoiners,
            $recommendedFormation,
            $notes,
            $sortOrder,
        ): RallyGroup {
            $group = RallyGroup::query()->create([
                'alliance_id' => $alliance->id,
                'occurrence_id' => $occurrence->id,
                'recommended_formation_id' => $recommendedFormation?->id,
                'name' => trim($name),
                'max_joiners' => $maxJoiners,
                'notes' => $notes === null ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
            ]);

            $this->audit->record('rally.group.created', $actor, $group, $alliance, [
                'occurrence_id' => $occurrence->id,
                'max_joiners' => $maxJoiners,
            ]);
            $this->outbox->record('rally.group.created', $alliance, $group, [
                'occurrence_id' => $occurrence->id,
            ]);

            return $group;
        });
    }
}
