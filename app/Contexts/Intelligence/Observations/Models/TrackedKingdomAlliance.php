<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Models;

use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $kingdom_alliance_id
 * @property string $kingdom_id
 * @property TrackedKingdomAllianceState $state
 * @property string|null $manager_notes
 * @property Carbon|null $archived_at
 * @property-read KingdomAllianceDiplomacy|null $diplomacy
 */
final class TrackedKingdomAlliance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'kingdom_alliance_id',
        'kingdom_id',
        'state',
        'manager_notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => TrackedKingdomAllianceState::class,
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<KingdomAllianceObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(KingdomAllianceObservation::class, 'tracked_kingdom_alliance_id');
    }

    /** @return HasOne<KingdomAllianceDiplomacy, $this> */
    public function diplomacy(): HasOne
    {
        return $this->hasOne(KingdomAllianceDiplomacy::class, 'tracked_kingdom_alliance_id');
    }
}
