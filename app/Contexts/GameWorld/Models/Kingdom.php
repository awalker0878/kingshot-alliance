<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Models;

use App\Contexts\GameWorld\Enums\KingdomStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Neutral KingShot Kingdom identity.
 *
 * @property int $number
 * @property KingdomStatus $status
 */
final class Kingdom extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => KingdomStatus::class,
        ];
    }
}
