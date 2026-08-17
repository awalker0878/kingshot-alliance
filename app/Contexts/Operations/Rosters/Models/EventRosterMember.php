<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Models;

use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventRosterMemberStatus $status
 * @property Carbon|null $assigned_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $removed_at
 * @property-read EventRoster $roster
 */
final class EventRosterMember extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'roster_id', 'player_id', 'alliance_id', 'role', 'slot_number', 'status', 'assignment_warnings',
        'assigned_by_player_id', 'assigned_at',
        'responded_by_player_id', 'responded_at',
        'removed_by_player_id', 'removed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventRosterMemberStatus::class,
            'slot_number' => 'integer',
            'assignment_warnings' => 'array',
            'assigned_at' => 'datetime',
            'responded_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function statusEnum(): EventRosterMemberStatus
    {
        return EventRosterMemberStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<EventRoster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(EventRoster::class, 'roster_id');
    }
}
