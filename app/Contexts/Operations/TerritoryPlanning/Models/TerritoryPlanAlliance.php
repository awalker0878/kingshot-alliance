<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $plan_key
 * @property string|null $alliance_id
 * @property string|null $external_name
 * @property string|null $external_tag
 * @property string $display_name
 * @property string $presentation_color
 * @property int $sort_order
 * @property bool $visible
 * @property bool $locked
 */
final class TerritoryPlanAlliance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'visible' => 'boolean',
        'locked' => 'boolean',
        'sort_order' => 'integer',
    ];
}
