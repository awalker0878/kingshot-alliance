<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RecruitmentNote extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['alliance_id', 'candidate_id', 'author_membership_id', 'body'];

    /** @return BelongsTo<RecruitmentCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function authorMembership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'author_membership_id');
    }
}
