<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
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
 * @property int $invited_by_user_id
 * @property int|null $accepted_by_user_id
 * @property int|null $declined_by_user_id
 * @property int|null $revoked_by_user_id
 * @property Carbon $invitation_expires_at
 * @property Carbon|null $invitation_used_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $revoked_at
 * @property-read Alliance $sourceAlliance
 * @property-read Alliance|null $recipientAlliance
 * @property-read Kingdom $kingdom
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
        'invited_by_user_id',
        'accepted_by_user_id',
        'declined_by_user_id',
        'revoked_by_user_id',
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

    /** @return BelongsTo<Alliance, $this> */
    public function sourceAlliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class, 'source_alliance_id');
    }

    /** @return BelongsTo<Alliance, $this> */
    public function recipientAlliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class, 'recipient_alliance_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function declinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
