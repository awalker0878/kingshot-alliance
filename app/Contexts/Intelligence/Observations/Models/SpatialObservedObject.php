<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Models;

use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property SpatialObservedObjectType $object_type
 * @property SpatialObservedIdentityState $identity_state
 * @property int $coordinate_x
 * @property int $coordinate_y
 * @property float|null $confidence
 * @property array<string,mixed>|null $source_metadata
 */
final class SpatialObservedObject extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'object_type' => SpatialObservedObjectType::class,
            'identity_state' => SpatialObservedIdentityState::class,
            'coordinate_x' => 'integer',
            'coordinate_y' => 'integer',
            'confidence' => 'float',
            'source_metadata' => 'array',
        ];
    }
}
