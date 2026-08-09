<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string $transfer_participant_id
 * @property TransferBlockerState $state
 * @property string $summary
 * @property string|null $details
 * @property int|null $created_by_user_id
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Alliance $alliance
 * @property-read TransferPlan $plan
 * @property-read TransferParticipant $participant
 * @property-read User|null $createdBy
 * @property-read User|null $resolvedBy
 */
final class TransferBlocker extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'transfer_participant_id',
        'state',
        'summary',
        'details',
        'created_by_user_id',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => TransferBlockerState::class,
            'resolved_at' => 'datetime',
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

    /** @return BelongsTo<TransferParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TransferParticipant::class, 'transfer_participant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
