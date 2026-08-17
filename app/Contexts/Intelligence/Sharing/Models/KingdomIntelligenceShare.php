<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Models;

use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $source_alliance_id
 * @property string|null $recipient_alliance_id
 * @property string $kingdom_id
 * @property string|null $invitation_token_hash
 * @property KingdomIntelligenceShareState $state
 * @property int $invited_by_player_id
 * @property int|null $accepted_by_player_id
 * @property int|null $declined_by_player_id
 * @property int|null $revoked_by_player_id
 * @property Carbon $invitation_expires_at
 * @property Carbon|null $invitation_used_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $revoked_at
 */
final class KingdomIntelligenceShare extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'source_alliance_id',
        'recipient_alliance_id',
        'kingdom_id',
        'invitation_token_hash',
        'state',
        'invited_by_player_id',
        'accepted_by_player_id',
        'declined_by_player_id',
        'revoked_by_player_id',
        'invitation_expires_at',
        'invitation_used_at',
        'accepted_at',
        'declined_at',
        'revoked_at',
    ];

    protected $hidden = [
        'invitation_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'state' => KingdomIntelligenceShareState::class,
            'invitation_expires_at' => 'datetime',
            'invitation_used_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
