<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source_key
 * @property string $name
 * @property string $classification
 * @property string|null $canonical_domain
 * @property bool $is_active
 * @property string $verification_method
 * @property array<string, mixed>|null $provenance_policy
 * @property bool $ingestion_enabled
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class GiftCodeSourceRegistry extends Model
{
    use HasUlids;

    protected $table = 'gift_code_sources';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'source_key',
        'name',
        'classification',
        'canonical_domain',
        'is_active',
        'verification_method',
        'provenance_policy',
        'ingestion_enabled',
        'revoked_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'provenance_policy' => 'array',
            'ingestion_enabled' => 'boolean',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<GiftCodeProvenance, $this> */
    public function provenances(): HasMany
    {
        return $this->hasMany(GiftCodeProvenance::class, 'registered_source_id');
    }
}
