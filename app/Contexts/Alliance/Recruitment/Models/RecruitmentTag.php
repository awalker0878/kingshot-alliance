<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class RecruitmentTag extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['alliance_id', 'name'];

    /** @return BelongsToMany<RecruitmentCandidate, $this> */
    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(
            RecruitmentCandidate::class,
            'recruitment_candidate_tags',
            'tag_id',
            'candidate_id',
        )->withPivot('alliance_id')->withTimestamps();
    }
}
