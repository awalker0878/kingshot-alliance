<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Recruitment\Enums\RecruitmentCommunicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $sent_at */
final class RecruitmentCommunication extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'candidate_id',
        'template_id',
        'channel',
        'subject',
        'body',
        'status',
        'idempotency_key',
        'created_by_user_id',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecruitmentCommunicationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RecruitmentCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    /** @return BelongsTo<RecruitmentDecisionTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RecruitmentDecisionTemplate::class, 'template_id');
    }
}
