<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventAttendance extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'player_id', 'status', 'notes',
        'recorded_by_player_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventAttendanceStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class); }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function recordedByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'recorded_by_player_id'); }
}
