<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Models;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read EventObjective $objective
 * @property-read EventOccurrence $occurrence
 * @property-read EventRoster|null $roster
 * @property-read Player|null $player
 */
final class EventObjectiveAssignment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['objective_id', 'occurrence_id', 'roster_id', 'player_id', 'assigned_by_player_id', 'assigned_at', 'notes'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    /** @return BelongsTo<EventObjective, $this> */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(EventObjective::class, 'objective_id');
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
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
}
