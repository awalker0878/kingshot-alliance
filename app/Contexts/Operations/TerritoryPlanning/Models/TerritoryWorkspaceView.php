<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $player_id
 * @property string $kingdom_id
 * @property string $map_dataset_id
 * @property string $map_dataset_checksum
 * @property int $revision
 * @property list<array<string, mixed>> $views
 */
final class TerritoryWorkspaceView extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'views' => 'array'];
    }
}
