<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $detected_at
 * @property Carbon|null $resolved_at
 */
final class ContributionDataQualityFlag extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'membership_id',
        'category_id',
        'record_id',
        'code',
        'severity',
        'message',
        'status',
        'detected_at',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
