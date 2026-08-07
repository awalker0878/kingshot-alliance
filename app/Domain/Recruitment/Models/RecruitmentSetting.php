<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Models;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property RecruitmentApplicationMode $application_mode
 * @property bool $is_open
 * @property int $retention_unsuccessful_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class RecruitmentSetting extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'application_mode',
        'title',
        'introduction',
        'retention_unsuccessful_days',
        'is_open',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'application_mode' => RecruitmentApplicationMode::class,
            'is_open' => 'boolean',
            'retention_unsuccessful_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
