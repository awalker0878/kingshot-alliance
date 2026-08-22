<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
