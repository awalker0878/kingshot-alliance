<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Models;

use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
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

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles', 'membership_id', 'role_id');
    }
}
