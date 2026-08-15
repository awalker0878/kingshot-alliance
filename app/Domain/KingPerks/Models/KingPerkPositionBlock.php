<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }

    public function sourceAppointment(): BelongsTo
    {
        return $this->belongsTo(KingPerkAppointment::class, 'source_appointment_id');
    }

    public function recordedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'recorded_by_player_id');
    }
}
