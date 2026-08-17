<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Models;

use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPushCategory;
use App\Contexts\Operations\KingPerks\Enums\KingPerkRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property KingPerkPushCategory $push_category
 * @property KingAppointmentType|null $preferred_appointment_type
 * @property Carbon $availability_starts_at
 * @property Carbon $availability_ends_at
 * @property int|null $planned_speedup_minutes
 * @property int|null $planned_resource_amount
 * @property KingPerkRequestStatus $status
 * @property Carbon|null $reviewed_at
 * @property-read KingPerkPlan|null $plan
 * @property-read KingPerkAppointment|null $scheduledAppointment
 */
final class KingPerkRequest extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'plan_id',
        'player_id',
        'push_category',
        'preferred_appointment_type',
        'availability_starts_at',
        'availability_ends_at',
        'planned_speedup_minutes',
        'planned_resource_amount',
        'notes',
        'status',
        'scheduled_appointment_id',
        'reviewed_by_player_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'push_category' => KingPerkPushCategory::class,
            'preferred_appointment_type' => KingAppointmentType::class,
            'availability_starts_at' => 'datetime',
            'availability_ends_at' => 'datetime',
            'planned_speedup_minutes' => 'integer',
            'planned_resource_amount' => 'integer',
            'status' => KingPerkRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KingPerkPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }

    /** @return BelongsTo<KingPerkAppointment, $this> */
    public function scheduledAppointment(): BelongsTo
    {
        return $this->belongsTo(KingPerkAppointment::class, 'scheduled_appointment_id');
    }

}
