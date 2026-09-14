<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $actor_player_id
 * @property string $mutation_id
 * @property string $request_checksum
 * @property int $accepted_revision
 * @property string $layout_checksum
 * @property array<string,mixed> $snapshot
 * @property list<string> $required_layer_keys
 * @property bool $requires_manage
 * @property Carbon $expires_at
 */
final class TerritorySaveReceipt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['accepted_revision' => 'integer', 'snapshot' => 'array',
            'required_layer_keys' => 'array', 'requires_manage' => 'boolean', 'expires_at' => 'datetime'];
    }
}
