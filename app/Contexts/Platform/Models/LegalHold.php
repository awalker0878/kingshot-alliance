<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $placed_at
 * @property Carbon|null $released_at
 */
final class LegalHold extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'reason',
        'placed_by_user_id',
        'placed_at',
        'released_by_user_id',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
