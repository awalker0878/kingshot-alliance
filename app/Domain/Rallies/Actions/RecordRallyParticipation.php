<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Events\Services\EventOutbox;
use App\Domain\Identity\Models\User;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordRallyParticipation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RallyAssignment $assignment,
        RallyAssignmentStatus $status,
    ): RallyAssignment {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to record rally participation.');
        }

        if ($assignment->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The rally assignment belongs to another alliance.');
        }

        if (! in_array($status, [RallyAssignmentStatus::Participated, RallyAssignmentStatus::NoShow], true)) {
            throw new InvalidArgumentException('Participation status must be participated or no-show.');
        }

        return DB::transaction(function () use ($actor, $alliance, $assignment, $status): RallyAssignment {
            $locked = RallyAssignment::query()
                ->whereKey($assignment->id)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'status' => $status,
                'participation_recorded_at' => now(),
            ])->save();

            $this->audit->record('rally.participation.recorded', $actor, $locked, $alliance, [
                'status' => $status->value,
            ]);
            $this->outbox->record('rally.participation.recorded', $alliance, $locked, [
                'status' => $status->value,
                'membership_id' => $locked->membership_id,
            ]);

            return $locked;
        });
    }
}
