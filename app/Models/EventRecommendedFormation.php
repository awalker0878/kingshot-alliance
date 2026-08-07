<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<int, string>|null $heroes */
final class EventRecommendedFormation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'occurrence_id',
        'guidance_rule_id',
        'name',
        'assignment_role',
        'heroes',
        'infantry_percent',
        'cavalry_percent',
        'archer_percent',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'heroes' => 'array',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<RallyGuidanceRule, $this> */
    public function guidanceRule(): BelongsTo
    {
        return $this->belongsTo(RallyGuidanceRule::class, 'guidance_rule_id');
    }
}
