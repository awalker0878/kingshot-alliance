<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Models;

use App\Contexts\Accounts\Models\User;
use App\Shared\Audit\Contracts\AuditActor;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable KingShot game identity and game-domain principal.
 *
 * @property int|null $user_id
 * @property string $current_kingdom_id
 * @property string|null $game_player_id
 * @property string $current_name
 * @property-read User|null $user
 * @property-read Kingdom $currentKingdom
 */
final class Player extends Model implements AuditActor
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'current_kingdom_id',
        'game_player_id',
        'current_name',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function currentKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'current_kingdom_id');
    }

    public function auditUserId(): ?int
    {
        return null;
    }

    public function auditPlayerId(): string
    {
        return (string) $this->id;
    }
}
