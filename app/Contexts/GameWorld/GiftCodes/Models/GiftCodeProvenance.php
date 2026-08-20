<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only evidence describing where a Gift Code was observed.
 *
 * @property string $id
 * @property string $gift_code_id
 * @property string|null $submitted_by_player_id
 * @property GiftCodeSource $source_type
 * @property string|null $source_label
 * @property string|null $source_url
 * @property CarbonImmutable $observed_at
 * @property string $fingerprint
 */
final class GiftCodeProvenance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'submitted_by_player_id',
        'source_type',
        'source_label',
        'source_url',
        'observed_at',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => GiftCodeSource::class,
            'observed_at' => 'immutable_datetime',
        ];
    }
}
