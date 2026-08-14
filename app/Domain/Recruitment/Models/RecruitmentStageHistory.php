<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Models;

use App\Domain\Recruitment\Enums\RecruitmentStage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $changed_at */
final class RecruitmentStageHistory extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'recruitment_stage_history';

    protected $fillable = [
        'alliance_id',
        'candidate_id',
        'from_stage',
        'to_stage',
        'reason',
        'changed_by_player_id',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => RecruitmentStage::class,
            'to_stage' => RecruitmentStage::class,
            'changed_at' => 'datetime',
        ];
    }

    public function fromStage(): ?RecruitmentStage
    {
        $value = $this->getAttribute('from_stage');
        if ($value === null) {
            return null;
        }

        return $value instanceof RecruitmentStage
            ? $value
            : RecruitmentStage::from((string) $value);
    }

    public function toStage(): RecruitmentStage
    {
        $value = $this->getAttribute('to_stage');

        return $value instanceof RecruitmentStage
            ? $value
            : RecruitmentStage::from((string) $value);
    }

    /** @return BelongsTo<RecruitmentCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }
}
