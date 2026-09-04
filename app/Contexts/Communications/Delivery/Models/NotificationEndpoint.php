<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Recipient-owned external delivery endpoint.
 *
 * Provider credentials and destinations are encrypted together so neither bot
 * tokens, push keys nor Discord webhook URLs are readable in a database snapshot.
 *
 * @property string $id
 * @property int $recipient_user_id
 * @property string|null $player_id
 * @property DeliveryChannel $channel
 * @property string $label
 * @property array<string, string> $configuration
 * @property bool $enabled
 * @property EndpointHealthStatus $health_status
 * @property CarbonImmutable|null $last_verified_at
 * @property CarbonImmutable|null $last_successful_delivery_at
 * @property CarbonImmutable|null $last_failed_delivery_at
 * @property int $consecutive_failures
 * @property string|null $last_error
 */
final class NotificationEndpoint extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recipient_user_id',
        'player_id',
        'channel',
        'label',
        'configuration',
        'enabled',
        'health_status',
        'last_verified_at',
        'last_successful_delivery_at',
        'last_failed_delivery_at',
        'consecutive_failures',
        'last_error',
    ];

    protected $hidden = ['configuration'];

    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'configuration' => 'encrypted:array',
            'enabled' => 'boolean',
            'health_status' => EndpointHealthStatus::class,
            'last_verified_at' => 'immutable_datetime',
            'last_successful_delivery_at' => 'immutable_datetime',
            'last_failed_delivery_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
        ];
    }
}
