<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Alliances\Models\Alliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property list<string> $events
 * @property string $signing_secret
 * @property bool $is_active
 * @property Carbon|null $revoked_at
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
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
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
