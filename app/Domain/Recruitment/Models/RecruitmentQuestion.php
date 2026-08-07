<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RecruitmentQuestion extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'prompt',
        'help_text',
        'question_type',
        'options',
        'is_required',
        'position',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => RecruitmentQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function type(): RecruitmentQuestionType
    {
        $value = $this->getAttribute('question_type');

        return $value instanceof RecruitmentQuestionType
            ? $value
            : RecruitmentQuestionType::from((string) $value);
    }

    /** @return list<string> */
    public function optionValues(): array
    {
        $options = $this->getAttribute('options');
        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(
            $options,
            static fn (mixed $option): bool => is_string($option),
        ));
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return HasMany<RecruitmentAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(RecruitmentAnswer::class, 'question_id');
    }
}
