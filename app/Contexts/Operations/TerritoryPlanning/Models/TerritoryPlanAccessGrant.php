<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $player_id
 * @property string $granted_by_player_id
 * @property string $alliance_key
 * @property string $permission
 * @property \Carbon\CarbonImmutable $expires_at
 * @property \Carbon\CarbonImmutable|null $revoked_at
 */
final class TerritoryPlanAccessGrant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
