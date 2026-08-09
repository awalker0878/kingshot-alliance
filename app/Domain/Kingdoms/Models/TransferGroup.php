<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferGroupState;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string $name
 * @property TransferDirection $direction
 * @property string|null $destination_kingdom_id
 * @property TransferGroupState $state
 * @property string|null $coordinator_membership_id
 * @property string|null $manager_notes
 * @property-read Alliance $alliance
 * @property-read TransferPlan $plan
 * @property-read Kingdom|null $destinationKingdom
 * @property-read AllianceMembership|null $coordinator
 */
final class TransferGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'name',
        'direction',
        'destination_kingdom_id',
        'state',
        'coordinator_membership_id',
        'manager_notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'state' => TransferGroupState::class,
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<TransferPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransferPlan::class, 'transfer_plan_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function destinationKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'destination_kingdom_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'coordinator_membership_id');
    }

    /** @return HasMany<TransferParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TransferParticipant::class, 'transfer_group_id');
    }
}
