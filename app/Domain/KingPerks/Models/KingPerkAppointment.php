<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkAppointmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KingPerkAppointment extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_id', 'appointment_type', 'assigned_player_id', 'starts_at', 'ends_at', 'status',
        'assigned_by_player_id', 'confirmed_at', 'actual_started_at', 'actual_ended_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'appointment_type' => KingAppointmentType::class,
            'status' => KingPerkAppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'actual_started_at' => 'datetime',
            'actual_ended_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo { return $this->belongsTo(KingPerkPlan::class, 'plan_id'); }
    public function assignedPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'assigned_player_id'); }
    public function assignedByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'assigned_by_player_id'); }
}
