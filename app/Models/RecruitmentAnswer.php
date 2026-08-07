<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RecruitmentAnswer extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'candidate_id',
        'question_id',
        'prompt_snapshot',
        'question_type_snapshot',
        'answer',
    ];

    protected function casts(): array
    {
        return [
            'question_type_snapshot' => RecruitmentQuestionType::class,
            'answer' => 'array',
        ];
    }

    /** @return BelongsTo<RecruitmentCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    /** @return BelongsTo<RecruitmentQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(RecruitmentQuestion::class, 'question_id');
    }
}
