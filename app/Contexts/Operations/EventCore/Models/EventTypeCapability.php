<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Models;

use App\Contexts\Operations\EventCore\Enums\EventCapability;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $event_type_scope_id
 * @property EventCapability $capability
 * @property array<string, mixed>|null $configuration
 * @property-read EventTypeScope $eventTypeScope
 */
final class EventTypeCapability extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_type_scope_id',
        'capability',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'capability' => EventCapability::class,
            'configuration' => 'array',
        ];
    }

    public function capabilityEnum(): EventCapability
    {
        $value = $this->getAttribute('capability');

        return $value instanceof EventCapability
            ? $value
            : EventCapability::from((string) $value);
    }

    /** @return BelongsTo<EventTypeScope, $this> */
    public function eventTypeScope(): BelongsTo
    {
        return $this->belongsTo(EventTypeScope::class);
    }
}
