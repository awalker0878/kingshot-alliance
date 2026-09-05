<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $accepted_count
 * @property int $corroborated_count
 * @property int $rejected_count
 * @property int $misleading_count
 * @property int $revision
 * @property CarbonImmutable $derived_at
 */
final class GiftCodeContributorProjection extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'accepted_count',
        'corroborated_count',
        'rejected_count',
        'misleading_count',
        'revision',
        'derived_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'accepted_count' => 'integer',
            'corroborated_count' => 'integer',
            'rejected_count' => 'integer',
            'misleading_count' => 'integer',
            'revision' => 'integer',
            'derived_at' => 'immutable_datetime',
        ];
    }
}
