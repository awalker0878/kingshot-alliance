<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventReminderDeliveryStatus $status
 * @property Carbon $due_at
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 */
final class EventReminderDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'occurrence_id',
        'rule_id',
        'membership_id',
        'due_at',
        'status',
        'attempts',
        'idempotency_key',
        'queued_at',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventReminderDeliveryStatus::class,
            'due_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<EventReminderRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(EventReminderRule::class, 'rule_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }
}
