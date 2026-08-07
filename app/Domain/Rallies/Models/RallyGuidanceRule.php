<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Models;

use App\Domain\Alliances\Models\Alliance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int, string>|null $hero_recommendations
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 */
final class RallyGuidanceRule extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'name',
        'lead_requirements',
        'joiner_guidance',
        'infantry_percent',
        'cavalry_percent',
        'archer_percent',
        'hero_recommendations',
        'notes',
        'effective_from',
        'effective_until',
        'source',
        'rationale',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'hero_recommendations' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
