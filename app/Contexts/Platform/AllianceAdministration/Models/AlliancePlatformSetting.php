<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Models;

use Illuminate\Database\Eloquent\Model;

final class AlliancePlatformSetting extends Model
{
    protected $primaryKey = 'alliance_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['alliance_id', 'retention_days', 'queue_partition', 'api_access_enabled', 'webhooks_enabled'];

    protected function casts(): array
    {
        return ['retention_days' => 'integer', 'api_access_enabled' => 'boolean', 'webhooks_enabled' => 'boolean'];
    }
}
