<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryObjectType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class TerritoryPlanObject extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'object_type' => TerritoryObjectType::class,
            'coordinate_x' => 'integer',
            'coordinate_y' => 'integer',
            'rotation' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
