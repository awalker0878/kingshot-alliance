<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingPerkPlanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class KingPerkPlan extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'occurrence_id', 'kingdom_id', 'status', 'window_starts_at', 'window_ends_at',
        'created_by_player_id', 'published_by_player_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => KingPerkPlanStatus::class,
            'window_starts_at' => 'datetime',
            'window_ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class); }
    public function kingdom(): BelongsTo { return $this->belongsTo(Kingdom::class); }
    public function createdByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'created_by_player_id'); }
    public function appointments(): HasMany { return $this->hasMany(KingPerkAppointment::class, 'plan_id')->orderBy('starts_at'); }
    public function positionBlocks(): HasMany { return $this->hasMany(KingPerkPositionBlock::class, 'plan_id')->orderBy('starts_at'); }
    public function skills(): HasMany { return $this->hasMany(KingSkillPlan::class, 'plan_id')->orderBy('planned_activation_at'); }
}
