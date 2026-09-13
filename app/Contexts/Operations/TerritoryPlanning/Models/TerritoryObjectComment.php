<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $object_key
 * @property string $alliance_key
 * @property string $author_player_id
 * @property int $head_revision
 * @property array<string,mixed> $object_snapshot
 * @property string $body
 */
final class TerritoryObjectComment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['object_snapshot' => 'array', 'head_revision' => 'integer'];
    }
}
