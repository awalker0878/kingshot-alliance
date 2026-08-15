<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Contexts\GameWorld\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property KingAppointmentType $appointment_type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property-read KingPerkPlan|null $plan
 * @property-read KingPerkAppointment|null $sourceAppointment
 * @property-read Player|null $recordedByPlayer
 */
final class KingPerkPositionBlock extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'plan_id', 'appointment_type', 'starts_at', 'ends_at', 'reason',
        'source_appointment_id', 'recorded_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'appointment_type' => KingAppointmentType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KingPerkPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }

    /** @return BelongsTo<KingPerkAppointment, $this> */
    public function sourceAppointment(): BelongsTo
    {
        return $this->belongsTo(KingPerkAppointment::class, 'source_appointment_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function recordedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'recorded_by_player_id');
    }
}
