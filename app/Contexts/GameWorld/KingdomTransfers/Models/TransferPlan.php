<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $home_kingdom_id
 * @property string $label
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property TransferPlanState $state
 * @property-read Kingdom $homeKingdom
 */
final class TransferPlan extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'home_kingdom_id',
        'label',
        'starts_on',
        'ends_on',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'state' => TransferPlanState::class,
        ];
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function homeKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'home_kingdom_id');
    }

    /** @return HasMany<TransferParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TransferParticipant::class, 'transfer_plan_id');
    }

    /** @return HasMany<TransferGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TransferGroup::class, 'transfer_plan_id');
    }
}
