<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventObjectiveAssignment extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['objective_id','occurrence_id','roster_id','player_id','assigned_by_player_id','assigned_at','notes'];
    protected function casts(): array { return ['assigned_at'=>'datetime']; }
    public function objective(): BelongsTo { return $this->belongsTo(EventObjective::class, 'objective_id'); }
    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class, 'occurrence_id'); }
    public function roster(): BelongsTo { return $this->belongsTo(EventRoster::class, 'roster_id'); }
    public function player(): BelongsTo { return $this->belongsTo(Player::class, 'player_id'); }
}
