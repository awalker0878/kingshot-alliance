<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Recipient-owned generic routing policy. Source-domain meaning never enters
 * this model.
 *
 * @property string $id
 * @property int $recipient_user_id
 * @property string|null $player_id
 * @property string $scope_key
 * @property string $timezone
 * @property bool $quiet_hours_enabled
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property bool $allow_urgent_during_quiet_hours
 * @property CarbonImmutable|null $muted_until
 * @property DigestCadence $digest_cadence
 * @property array<string,mixed>|null $settings
 */
final class NotificationRoutingPolicy extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recipient_user_id',
        'player_id',
        'scope_key',
        'timezone',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'allow_urgent_during_quiet_hours',
        'muted_until',
        'digest_cadence',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'quiet_hours_enabled' => 'boolean',
            'allow_urgent_during_quiet_hours' => 'boolean',
            'muted_until' => 'immutable_datetime',
            'digest_cadence' => DigestCadence::class,
            'settings' => 'array',
        ];
    }
}
