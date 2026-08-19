<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Models;

use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property RallyAssignmentRole $role
 * @property RallyAssignmentStatus $status
 * @property Carbon|null $assigned_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $recorded_at
 * @property Carbon|null $removed_at
 * @property-read RallyGroup $rallyGroup
 */
final class RallyAssignment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['rally_group_id', 'player_id', 'role', 'slot_number', 'status', 'assigned_by_player_id', 'assigned_at', 'responded_by_player_id', 'responded_at', 'recorded_by_player_id', 'recorded_at', 'removed_by_player_id', 'removed_at', 'notes'];

    protected function casts(): array
    {
        return [
            'role' => RallyAssignmentRole::class,
            'slot_number' => 'integer',
            'status' => RallyAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'responded_at' => 'datetime',
            'recorded_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function roleEnum(): RallyAssignmentRole
    {
        return RallyAssignmentRole::from((string) $this->getRawOriginal('role'));
    }

    public function statusEnum(): RallyAssignmentStatus
    {
        return RallyAssignmentStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<RallyGroup, $this> */
    public function rallyGroup(): BelongsTo
    {
        return $this->belongsTo(RallyGroup::class);
    }
}
