<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Models;

use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EventObjectiveAssignment::class, 'objective_id');
    }
}
