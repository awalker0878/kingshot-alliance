<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Models;

use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $objective_type
 * @property string $name
 * @property string|null $description
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property EventObjectiveStatus $status
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property-read EventOccurrence $occurrence
 * @property-read EventObjective|null $parent
 * @property-read EloquentCollection<int, EventObjectiveAssignment> $assignments
 */
final class EventObjective extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['occurrence_id', 'parent_id', 'objective_type', 'name', 'description', 'priority', 'starts_at', 'ends_at', 'status', 'sort_order', 'metadata', 'created_by_player_id', 'updated_by_player_id'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'status' => EventObjectiveStatus::class, 'sort_order' => 'integer', 'metadata' => 'array'];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<EventObjective, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<EventObjective, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<EventObjectiveAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(EventObjectiveAssignment::class, 'objective_id');
    }
}
