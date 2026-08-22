<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TerritoryPlan extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope' => TerritoryPlanScope::class,
            'status' => TerritoryPlanStatus::class,
            'revision' => 'integer',
            'planning_preferences' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<TerritoryPlanAlliance,$this> */
    public function planAlliances(): HasMany
    {
        return $this->hasMany(TerritoryPlanAlliance::class)->orderBy('sort_order');
    }

    /** @return HasMany<TerritoryPlanGroup,$this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TerritoryPlanGroup::class);
    }

    /** @return HasMany<TerritoryPlanObject,$this> */
    public function objects(): HasMany
    {
        return $this->hasMany(TerritoryPlanObject::class)->orderBy('sort_order');
    }

    /** @return HasMany<TerritoryPlanRevision,$this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(TerritoryPlanRevision::class)->orderByDesc('revision_number');
    }
}
