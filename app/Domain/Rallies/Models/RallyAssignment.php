<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
