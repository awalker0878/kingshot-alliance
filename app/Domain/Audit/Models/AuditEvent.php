<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class AuditEvent extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'actor_user_id',
        'actor_player_id',
        'event',
        'subject_type',
        'subject_id',
        'metadata',
        'request_id',
        'trace_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function actorPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'actor_player_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
