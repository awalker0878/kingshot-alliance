<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkAppointmentStatus;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class KingPerkAppointment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'plan_id', 'appointment_type', 'assigned_player_id', 'starts_at', 'ends_at', 'player_cooldown_ends_at', 'status',
        'assigned_by_player_id', 'confirmed_at', 'actual_started_at', 'actual_ended_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'appointment_type' => KingAppointmentType::class,
            'status' => KingPerkAppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'player_cooldown_ends_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'actual_started_at' => 'datetime',
            'actual_ended_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::saving(static function (self $appointment): void {
            if ($appointment->exists && ! $appointment->isDirty(['appointment_type', 'starts_at'])) {
                return;
            }

            $type = $appointment->appointmentType();
            $start = $appointment->startsAt();
            $end = $start->addMinutes($type->durationMinutes());
            $appointment->ends_at = $end;
            $appointment->player_cooldown_ends_at = $end->addMinutes($type->playerCooldownMinutes());
        });
    }

    public function appointmentType(): KingAppointmentType
    {
        $value = $this->getAttribute('appointment_type');

        return $value instanceof KingAppointmentType ? $value : KingAppointmentType::from((string) $value);
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->immutableDate('starts_at');
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->immutableDate('ends_at');
    }

    public function playerCooldownEndsAt(): CarbonImmutable
    {
        return $this->immutableDate('player_cooldown_ends_at');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }

    public function assignedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'assigned_player_id');
    }

    public function assignedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'assigned_by_player_id');
    }

    private function immutableDate(string $attribute): CarbonImmutable
    {
        $value = $this->getAttribute($attribute);
        if (! $value instanceof DateTimeInterface) {
            throw new LogicException(sprintf('King Perks appointment %s must be a date-time.', $attribute));
        }

        return CarbonImmutable::instance($value);
    }
}
