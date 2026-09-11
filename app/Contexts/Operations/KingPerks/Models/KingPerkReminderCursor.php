<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Models;

use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner-private traversal progress, not delivery, membership or source authority.
 * A new primary identity after pruning fences a stalled worker from a recreated cursor.
 *
 * @property string $id
 * @property string $source_id
 * @property KingPerkReminderKind $kind
 * @property string|null $after_player_id
 * @property int $version
 * @property CarbonImmutable $visited_at
 * @property CarbonImmutable $expires_at
 */
final class KingPerkReminderCursor extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected $fillable = ['kind', 'source_id', 'after_player_id', 'version', 'visited_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'kind' => KingPerkReminderKind::class,
            'version' => 'integer',
            'visited_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
