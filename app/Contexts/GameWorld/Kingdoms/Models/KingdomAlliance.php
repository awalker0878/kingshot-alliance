<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Models;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $kingdom_id
 * @property string|null $game_alliance_id
 * @property string $current_name
 * @property string|null $current_tag
 * @property KingdomAllianceStatus $status
 * @property-read Kingdom $kingdom
 */
final class KingdomAlliance extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['kingdom_id', 'game_alliance_id', 'current_name', 'current_tag', 'status'];

    protected function casts(): array
    {
        return ['status' => KingdomAllianceStatus::class];
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }
}
