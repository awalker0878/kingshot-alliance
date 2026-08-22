<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property int $revision_number
 * @property int $schema_version
 * @property string $map_dataset_id
 * @property string $map_dataset_checksum
 * @property array<string, mixed> $snapshot
 * @property string $snapshot_checksum
 * @property string $published_by_player_id
 * @property Carbon $published_at
 * @property Carbon $created_at
 */
final class TerritoryPlanRevision extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'schema_version' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
