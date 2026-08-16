<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Models;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property EventPollType $poll_type
 * @property EventPollStatus $status
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property int $max_choices
 * @property array<string, mixed>|null $settings
 * @property-read EventOccurrence $occurrence
 */
final class EventPoll extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'key', 'poll_type', 'question_key', 'question', 'opens_at', 'closes_at', 'status', 'max_choices', 'settings',
        'created_by_player_id', 'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'poll_type' => EventPollType::class,
            'status' => EventPollStatus::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'max_choices' => 'integer',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return HasMany<EventPollOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(EventPollOption::class, 'poll_id')->orderBy('sort_order');
    }

    /** @return HasMany<EventPollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(EventPollVote::class, 'poll_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function createdByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function updatedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'updated_by_player_id');
    }
}
