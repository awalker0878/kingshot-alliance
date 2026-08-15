<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Models;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class RecruitmentDecisionTemplate extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'name',
        'decision_stage',
        'subject',
        'body',
        'is_active',
        'created_by_player_id',
        'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'decision_stage' => RecruitmentStage::class,
            'is_active' => 'boolean',
        ];
    }

    public function decisionStage(): RecruitmentStage
    {
        $value = $this->getAttribute('decision_stage');

        return $value instanceof RecruitmentStage
            ? $value
            : RecruitmentStage::from((string) $value);
    }
}
