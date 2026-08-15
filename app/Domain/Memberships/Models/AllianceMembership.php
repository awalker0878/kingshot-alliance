<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Models\Role;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $player_id
 * @property MembershipStatus $status
 * @property AllianceRank $rank
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Player $player
 */
final class AllianceMembership extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'rank' => 'r1',
    ];

    protected $fillable = [
        'alliance_id',
        'player_id',
        'status',
        'rank',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'rank' => AllianceRank::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles', 'membership_id', 'role_id');
    }
}
