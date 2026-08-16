<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable KingShot game identity and game-domain principal.
 *
 * The owning account is represented by the scalar user_id boundary key. GameWorld
 * deliberately exposes no Eloquent navigation back into Accounts.
 *
 * @property int|null $user_id
 * @property string $current_kingdom_id
 * @property string|null $game_player_id
 * @property string $current_name
 * @property-read Kingdom $currentKingdom
 */
final class Player extends Model implements AuditActor
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'current_kingdom_id', 'game_player_id', 'current_name'];

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
