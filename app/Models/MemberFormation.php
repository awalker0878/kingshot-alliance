<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<int, string>|null $heroes */
final class MemberFormation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'membership_id',
        'name',
        'heroes',
        'infantry_percent',
        'cavalry_percent',
        'archer_percent',
        'notes',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'heroes' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }
}
