<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Models;

use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property RallyAssignmentRole $role
 * @property RallyAssignmentStatus $status
 * @property Carbon|null $participation_recorded_at
 */
final class RallyAssignment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'rally_group_id',
        'membership_id',
        'role',
        'slot_number',
        'status',
        'participation_recorded_at',
        'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => RallyAssignmentRole::class,
            'status' => RallyAssignmentStatus::class,
            'participation_recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RallyGroup, $this> */
    public function rallyGroup(): BelongsTo
    {
        return $this->belongsTo(RallyGroup::class);
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }
}
