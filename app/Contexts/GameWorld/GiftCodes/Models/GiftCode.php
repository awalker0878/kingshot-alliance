<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $normalized_code
 * @property GiftCodeSource $source_type
 * @property string|null $source_label
 * @property string|null $source_url
 * @property string|null $created_by_player_id
 * @property GiftCodeStatus $status
 * @property CarbonImmutable|null $status_changed_at
 * @property CarbonImmutable $discovered_at
 * @property CarbonImmutable|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, GiftCodeRedemption> $redemptions
 * @property-read Collection<int, GiftCodeProvenance> $provenances
 */
final class GiftCode extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'normalized_code',
        'source_type',
        'source_label',
        'source_url',
        'created_by_player_id',
        'status',
        'status_changed_at',
        'discovered_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => GiftCodeSource::class,
            'status' => GiftCodeStatus::class,
            'status_changed_at' => 'immutable_datetime',
            'discovered_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<GiftCodeRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCodeRedemption::class);
    }

    /** @return HasMany<GiftCodeProvenance, $this> */
    public function provenances(): HasMany
    {
        return $this->hasMany(GiftCodeProvenance::class)->orderByDesc('observed_at');
    }
}
