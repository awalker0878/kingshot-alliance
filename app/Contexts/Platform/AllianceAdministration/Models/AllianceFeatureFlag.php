<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AllianceFeatureFlag extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['alliance_id', 'feature_key', 'enabled', 'configuration', 'updated_by_user_id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'configuration' => 'array'];
    }
}
