<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkPushCategory;
use App\Domain\KingPerks\Enums\KingPerkRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function scheduledAppointment(): BelongsTo
    {
        return $this->belongsTo(KingPerkAppointment::class, 'scheduled_appointment_id');
    }

    public function reviewedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'reviewed_by_player_id');
    }
}
