<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string $name
 * @property Carbon|null $archived_at
 * @property-read Alliance $alliance
 * @property-read TransferPlan $plan
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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
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

    /** @return HasMany<TransferParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TransferParticipant::class, 'transfer_group_id');
    }

    /** @return BelongsToMany<AllianceMembership, $this> */
    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(
            AllianceMembership::class,
            'transfer_group_coordinators',
            'transfer_group_id',
            'membership_id',
        )->withPivot(['alliance_id', 'transfer_plan_id'])->withTimestamps();
    }
}
