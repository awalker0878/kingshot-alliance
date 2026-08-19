<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventPhaseStatus;
use App\Contexts\Operations\Events\Enums\EventPhaseType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventPhaseType $phase_type
 * @property EventPhaseStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $sort_order
 * @property array<string, mixed>|null $settings
 */
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

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }
}
