<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Authorization\Models\KingdomRoleAssignment;

/**
 * Durable KingShot game identity.
 *
 * @property int|null $user_id
 * @property string $current_kingdom_id
 * @property string|null $game_player_id
 * @property string $current_name
 * @property-read User|null $user
 * @property-read Kingdom $currentKingdom
 */
final class Player extends Model
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

    /** @return HasMany<AllianceMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AllianceMembership::class);
    }

    /** @return HasMany<KingdomRoleAssignment, $this> */
    public function kingdomRoleAssignments(): HasMany
    {
        return $this->hasMany(KingdomRoleAssignment::class);
    }

    /** @return HasMany<AllianceRosterEntry, $this> */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(AllianceRosterEntry::class);
    }
}
