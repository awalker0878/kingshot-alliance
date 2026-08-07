<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ContributionRecordSource $source
 * @property ContributionDataClass $data_class
 * @property ContributionRecordStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $recorded_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $reversed_at
 */
final class ContributionRecord extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'category_id',
        'membership_id',
        'source',
        'data_class',
        'value',
        'period_start',
        'period_end',
        'status',
        'evidence',
        'event_registration_id',
        'correction_of_record_id',
        'calculation_key',
        'calculation_version',
        'calculation_inputs',
        'recorded_at',
        'recorded_by_user_id',
        'approved_at',
        'approved_by_user_id',
        'reversed_at',
        'reversed_by_user_id',
        'reversal_reason',
        'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'source' => ContributionRecordSource::class,
            'data_class' => ContributionDataClass::class,
            'value' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => ContributionRecordStatus::class,
            'calculation_inputs' => 'array',
            'recorded_at' => 'datetime',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ContributionCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ContributionCategory::class, 'category_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }

    /** @return BelongsTo<ContributionRecord, $this> */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_record_id');
    }
}
