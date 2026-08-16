<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Models;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
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
        'player_id',
        'source',
        'data_class',
        'value',
        'period_start',
        'period_end',
        'status',
        'evidence',
        'correction_of_record_id',
        'calculation_key',
        'calculation_version',
        'calculation_inputs',
        'recorded_at',
        'recorded_by_player_id',
        'approved_at',
        'approved_by_player_id',
        'reversed_at',
        'reversed_by_player_id',
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

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /** @return BelongsTo<ContributionRecord, $this> */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_record_id');
    }
}
