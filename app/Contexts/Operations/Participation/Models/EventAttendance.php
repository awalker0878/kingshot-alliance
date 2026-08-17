<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Models;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventAttendanceStatus $status
 * @property Carbon|null $recorded_at
 * @property-read EventOccurrence $occurrence
 */
final class EventAttendance extends Model
{
    use HasUlids;

    protected $table = 'event_attendance';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'player_id', 'status', 'notes',
        'recorded_by_player_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['status' => EventAttendanceStatus::class, 'recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }
}
