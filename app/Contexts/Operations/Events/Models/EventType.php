<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $slug
 * @property string $name_key
 * @property string|null $description_key
 * @property EventCategory $category
 * @property string|null $icon_key
 * @property bool $is_system
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Collection<int, EventTypeScope> $scopes
 */
final class EventType extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name_key',
        'description_key',
        'category',
        'icon_key',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => EventCategory::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function categoryEnum(): EventCategory
    {
        $value = $this->getAttribute('category');

        return $value instanceof EventCategory ? $value : EventCategory::from((string) $value);
    }

    /** @return HasMany<EventTypeScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(EventTypeScope::class);
    }
}
