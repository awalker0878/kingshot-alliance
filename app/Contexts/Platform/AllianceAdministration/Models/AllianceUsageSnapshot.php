<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AllianceUsageSnapshot extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['alliance_id', 'active_members', 'storage_bytes', 'active_api_credentials', 'active_webhook_subscriptions', 'pending_outbox_messages', 'captured_at'];

    protected function casts(): array
    {
        return ['active_members' => 'integer', 'storage_bytes' => 'integer', 'active_api_credentials' => 'integer', 'active_webhook_subscriptions' => 'integer', 'pending_outbox_messages' => 'integer', 'captured_at' => 'datetime'];
    }
}
