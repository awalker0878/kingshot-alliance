<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property list<string> $events
 * @property string $signing_secret
 * @property bool $is_active
 * @property Carbon|null $revoked_at
 * @property Carbon|null $secret_rotated_at
 */
final class WebhookSubscription extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'name',
        'url',
        'events',
        'signing_secret',
        'secret_rotated_at',
        'is_active',
        'revoked_at',
        'created_by_player_id',
    ];

    protected $hidden = ['signing_secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'signing_secret' => 'encrypted',
            'secret_rotated_at' => 'datetime',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function receives(string $eventType): bool
    {
        return $this->is_active
            && $this->revoked_at === null
            && (in_array('*', $this->events, true) || in_array($eventType, $this->events, true));
    }
}
