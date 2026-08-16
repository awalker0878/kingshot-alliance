<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Recipient/channel preference owned by Communications.
 *
 * Recipient identities remain scalar references across the Accounts/GameWorld boundary.
 */
final class NotificationPreference extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recipient_user_id',
        'player_id',
        'notification_type',
        'channel',
        'enabled',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
        ];
    }
}
