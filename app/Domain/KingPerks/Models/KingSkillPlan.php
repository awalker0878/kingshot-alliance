<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingSkill;
use App\Domain\KingPerks\Enums\KingSkillStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KingSkillPlan extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_id', 'skill_key', 'planned_activation_at', 'effect_duration_minutes', 'planned_ends_at',
        'status', 'planned_by_player_id', 'scheduled_by_player_id', 'activated_by_player_id',
        'scheduled_in_game_at', 'activated_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'skill_key' => KingSkill::class,
            'status' => KingSkillStatus::class,
            'planned_activation_at' => 'datetime',
            'planned_ends_at' => 'datetime',
            'effect_duration_minutes' => 'integer',
            'scheduled_in_game_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo { return $this->belongsTo(KingPerkPlan::class, 'plan_id'); }
    public function plannedByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'planned_by_player_id'); }
}
