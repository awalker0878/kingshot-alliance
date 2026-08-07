<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Memberships\Models\AllianceMembership;

use App\Domain\Events\Enums\EventRegistrationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventRegistrationStatus $status
 * @property Carbon $registered_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $attendance_recorded_at
 */
final class EventRegistration extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'occurrence_id',
        'membership_id',
        'status',
        'waitlist_position',
        'registered_at',
        'cancelled_at',
        'attendance_recorded_at',
        'attendance_recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventRegistrationStatus::class,
            'registered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attendance_recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }
}
