<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Models;

use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlayerFormation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['player_id', 'name', 'infantry_percent', 'cavalry_percent', 'archer_percent', 'heroes', 'notes', 'is_default', 'created_by_player_id', 'updated_by_player_id'];

    protected function casts(): array
    {
        return ['infantry_percent' => 'integer', 'cavalry_percent' => 'integer', 'archer_percent' => 'integer', 'heroes' => 'array', 'is_default' => 'boolean'];
    }

    /** @return BelongsTo<Player,$this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
