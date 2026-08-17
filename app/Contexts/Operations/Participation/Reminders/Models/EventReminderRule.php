<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Models;

use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Polls\Models\EventPoll;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property EventReminderTrigger $trigger_type
 * @property EventReminderAudience $audience
 * @property-read Event $event
 * @property-read EventPoll|null $poll
 */
final class EventReminderRule extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'poll_id', 'trigger_type', 'minutes_before', 'audience', 'channel', 'is_enabled',
        'created_by_player_id', 'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => EventReminderTrigger::class,
            'minutes_before' => 'integer',
            'audience' => EventReminderAudience::class,
            'is_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventPoll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(EventPoll::class, 'poll_id');
    }
}
