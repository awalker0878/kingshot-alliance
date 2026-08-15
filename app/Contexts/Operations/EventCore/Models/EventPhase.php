<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Models;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventPhaseStatus;
use App\Contexts\Operations\EventCore\Enums\EventPhaseType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventPhase extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'key', 'name_key', 'name', 'phase_type', 'starts_at', 'ends_at', 'status', 'sort_order', 'settings',
        'created_by_player_id', 'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'phase_type' => EventPhaseType::class,
            'status' => EventPhaseStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function createdByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    public function updatedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'updated_by_player_id');
    }
}
