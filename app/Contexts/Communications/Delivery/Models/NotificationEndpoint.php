<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Recipient-owned external delivery endpoint.
 *
 * Provider credentials and destinations are encrypted together so neither bot
 * tokens nor Discord webhook URLs are readable in a database snapshot.
 *
 * @property string $id
 * @property int $recipient_user_id
 * @property string|null $player_id
 * @property DeliveryChannel $channel
 * @property string $label
 * @property array<string, string> $configuration
 * @property bool $enabled
 * @property CarbonImmutable|null $last_verified_at
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
        'last_verified_at',
        'last_error',
    ];

    protected $hidden = ['configuration'];

    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'configuration' => 'encrypted:array',
            'enabled' => 'boolean',
            'last_verified_at' => 'immutable_datetime',
        ];
    }
}
