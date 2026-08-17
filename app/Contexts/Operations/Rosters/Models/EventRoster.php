<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Models;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $key
 * @property string|null $name_key
 * @property string|null $name
 * @property EventRosterType $roster_type
 * @property int $sort_order
 * @property-read EventOccurrence $occurrence
 */
final class EventRoster extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'parent_id', 'key', 'name_key', 'name', 'roster_type', 'assignment_group', 'capacity', 'sort_order', 'settings',
        'created_by_player_id', 'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'roster_type' => EventRosterType::class,
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<EventRosterMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(EventRosterMember::class, 'roster_id')->orderBy('slot_number')->orderBy('assigned_at');
    }
}
