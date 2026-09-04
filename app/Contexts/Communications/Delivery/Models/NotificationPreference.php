<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Recipient/channel preference owned by Communications.
 *
 * `scope_key=account` represents the account default; a Governor override uses
 * its concrete Player ID as both `player_id` and `scope_key`.
 */
final class NotificationPreference extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recipient_user_id',
        'player_id',
        'scope_key',
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
