<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function assignedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'assigned_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function respondedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'responded_by_player_id');
    }
}
