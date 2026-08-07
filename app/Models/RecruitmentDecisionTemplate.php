<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Recruitment\Enums\RecruitmentStage;
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
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'decision_stage' => RecruitmentStage::class,
            'is_active' => 'boolean',
        ];
    }
}
