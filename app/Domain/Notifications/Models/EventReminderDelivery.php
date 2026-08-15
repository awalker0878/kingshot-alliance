<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Events\Models\EventOccurrence;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventReminderDeliveryStatus $status
 * @property Carbon $due_at
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventReminderRule|null $rule
 * @property-read User|null $recipientUser
 * @property-read Player|null $player
 */
final class EventReminderDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'rule_id', 'recipient_user_id', 'player_id', 'due_at', 'status',
        'attempts', 'idempotency_key', 'queued_at', 'sent_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => EventReminderDeliveryStatus::class,
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    /** @return BelongsTo<EventReminderRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(EventReminderRule::class, 'rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
