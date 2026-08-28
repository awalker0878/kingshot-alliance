<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class SpatialObservationEvidenceReceipt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
