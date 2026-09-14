<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $territory_plan_id
 * @property string $actor_player_id
 * @property int $base_revision
 * @property string $map_dataset_id
 * @property string $map_dataset_checksum
 * @property array<string,mixed> $document
 * @property string $document_checksum
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class TerritoryRecoveryDraft extends Model
{
    use HasUlids;

    protected $fillable = [
        'territory_plan_id',
        'actor_player_id',
        'base_revision',
        'map_dataset_id',
        'map_dataset_checksum',
        'document',
        'document_checksum',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'base_revision' => 'integer',
            'document' => 'array',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
