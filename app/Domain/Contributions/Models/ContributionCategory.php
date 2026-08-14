<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ContributionPeriod $period
 * @property ContributionDataClass $data_class
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
final class ContributionCategory extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'name',
        'slug',
        'description',
        'unit',
        'period',
        'period_start',
        'period_end',
        'goal_value',
        'evidence_required',
        'allow_self_report',
        'leaderboard_enabled',
        'data_class',
        'calculation_key',
        'calculation_version',
        'calculation_description',
        'is_active',
        'created_by_player_id',
        'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'period' => ContributionPeriod::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'goal_value' => 'decimal:2',
            'evidence_required' => 'boolean',
            'allow_self_report' => 'boolean',
            'leaderboard_enabled' => 'boolean',
            'data_class' => ContributionDataClass::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ContributionRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(ContributionRecord::class, 'category_id');
    }
}
