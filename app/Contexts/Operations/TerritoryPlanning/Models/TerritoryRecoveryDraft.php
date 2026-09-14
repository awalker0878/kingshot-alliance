<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
        ];
    }
}
