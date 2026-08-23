<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $home_kingdom_id
 * @property string $transfer_window_id
 * @property string $label
 * @property TransferPlanState $state
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Kingdom $homeKingdom
 * @property-read TransferWindow $window
 * @property-read Collection<int, TransferParticipant> $participants
 * @property-read Collection<int, TransferCohort> $cohorts
 */
final class TransferPlan extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['state' => TransferPlanState::class];
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function homeKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'home_kingdom_id');
    }

    /** @return BelongsTo<TransferWindow, $this> */
    public function window(): BelongsTo
    {
        return $this->belongsTo(TransferWindow::class, 'transfer_window_id');
    }

    /** @return HasMany<TransferParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TransferParticipant::class, 'transfer_plan_id');
    }

    /** @return HasMany<TransferCohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(TransferCohort::class, 'transfer_plan_id');
    }
}
