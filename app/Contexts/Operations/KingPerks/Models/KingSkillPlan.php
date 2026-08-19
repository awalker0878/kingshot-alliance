<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Models;

use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property KingSkill $skill_key
 * @property KingSkillStatus $status
 * @property Carbon $planned_activation_at
 * @property Carbon $planned_ends_at
 * @property int $effect_duration_minutes
 * @property Carbon|null $scheduled_in_game_at
 * @property Carbon|null $activated_at
 * @property-read KingPerkPlan $plan
 */
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

    /** @return BelongsTo<KingPerkPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(KingPerkPlan::class, 'plan_id');
    }
}
