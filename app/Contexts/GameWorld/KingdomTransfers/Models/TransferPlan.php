<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TransferPlan extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['state' => TransferPlanState::class];
    }

    public function homeKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'home_kingdom_id');
    }

    public function window(): BelongsTo
    {
        return $this->belongsTo(TransferWindow::class, 'transfer_window_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TransferParticipant::class, 'transfer_plan_id');
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(TransferCohort::class, 'transfer_plan_id');
    }
}
