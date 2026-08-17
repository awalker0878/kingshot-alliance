<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Models;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
