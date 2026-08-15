<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\KingPerks\Enums\KingPerkReminderKind;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingSkillPlan;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property KingPerkReminderKind $kind
 * @property EventReminderDeliveryStatus $status
 * @property Carbon $due_at
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property-read KingPerkPlan $plan
 * @property-read KingPerkAppointment|null $appointment
 * @property-read KingSkillPlan|null $skillPlan
 * @property-read Player $player
 * @property-read User $recipientUser
 */
final class KingPerkReminderDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'plan_id',
        'appointment_id',
        'skill_plan_id',
        'player_id',
        'recipient_user_id',
        'kind',
        'due_at',
        'status',
        'idempotency_key',
        'queued_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => KingPerkReminderKind::class,
            'due_at' => 'datetime',
            'status' => EventReminderDeliveryStatus::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KingPerkPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }

    /** @return BelongsTo<KingPerkAppointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(KingPerkAppointment::class, 'appointment_id');
    }

    /** @return BelongsTo<KingSkillPlan, $this> */
    public function skillPlan(): BelongsTo
    {
        return $this->belongsTo(KingSkillPlan::class, 'skill_plan_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
