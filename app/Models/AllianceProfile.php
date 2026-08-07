<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Content\Enums\RecruitmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property RecruitmentStatus $recruitment_status */
final class AllianceProfile extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'alliance_id';

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'description',
        'recruitment_status',
        'primary_color',
    ];

    protected function casts(): array
    {
        return [
            'recruitment_status' => RecruitmentStatus::class,
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
