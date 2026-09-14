<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $territory_plan_revision_id
 * @property string $recipient_player_id
 * @property string $created_by_player_id
 * @property string $token_hash
 * @property list<string> $alliance_keys
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 */
final class TerritoryShare extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['alliance_keys' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
