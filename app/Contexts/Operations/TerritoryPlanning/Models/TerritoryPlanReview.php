<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $reviewer_player_id
 * @property int $head_revision
 * @property string $snapshot_checksum
 * @property string $decision
 * @property string|null $note
 */
final class TerritoryPlanReview extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['head_revision' => 'integer'];
    }
}
