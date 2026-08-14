<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventPollStatus;
use App\Domain\Events\Enums\EventPollType;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class, 'occurrence_id'); }
    public function options(): HasMany { return $this->hasMany(EventPollOption::class, 'poll_id')->orderBy('sort_order'); }
    public function votes(): HasMany { return $this->hasMany(EventPollVote::class, 'poll_id'); }
    public function createdByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'created_by_player_id'); }
    public function updatedByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'updated_by_player_id'); }
}
