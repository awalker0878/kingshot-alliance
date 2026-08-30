<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftCodeFactProjection extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'fact_type',
        'qualified',
        'reason_code',
        'value',
        'evidence_ids',
        'revision',
        'derived_at',
    ];

    protected function casts(): array
    {
        return [
            'qualified' => 'boolean',
            'value' => 'array',
            'evidence_ids' => 'array',
            'revision' => 'integer',
            'derived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }
}
