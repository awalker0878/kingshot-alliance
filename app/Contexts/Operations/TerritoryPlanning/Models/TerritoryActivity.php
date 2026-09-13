<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $actor_player_id
 * @property string $kind
 * @property string $meaning_key
 * @property list<string> $recipient_player_ids
 * @property string|null $after_player_id
 * @property \Carbon\CarbonImmutable|null $completed_at
 */
final class TerritoryActivity extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipient_player_ids' => 'array', 'completed_at' => 'immutable_datetime'];
    }
}
