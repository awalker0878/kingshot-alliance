<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryObjectType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $plan_key
 * @property string $territory_plan_alliance_id
 * @property string|null $group_id
 * @property TerritoryObjectType $object_type
 * @property string|null $player_id
 * @property string|null $external_player_name
 * @property string|null $label
 * @property int $coordinate_x
 * @property int $coordinate_y
 * @property int $rotation
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 */
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
