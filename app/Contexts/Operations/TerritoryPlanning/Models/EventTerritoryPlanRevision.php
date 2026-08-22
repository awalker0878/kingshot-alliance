<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_occurrence_id
 * @property string $territory_plan_revision_id
 * @property string $purpose
 * @property string $created_by_player_id
 * @property Carbon $created_at
 */
final class EventTerritoryPlanRevision extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
