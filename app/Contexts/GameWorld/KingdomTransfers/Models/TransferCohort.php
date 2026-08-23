<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCohortState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TransferCohort extends Model
{
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['direction' => TransferDirection::class, 'state' => TransferCohortState::class]; }
    public function plan(): BelongsTo { return $this->belongsTo(TransferPlan::class, 'transfer_plan_id'); }
    public function destinationKingdom(): BelongsTo { return $this->belongsTo(Kingdom::class, 'destination_kingdom_id'); }
    public function coordinator(): BelongsTo { return $this->belongsTo(Player::class, 'coordinator_player_id'); }
    public function participants(): HasMany { return $this->hasMany(TransferParticipant::class, 'transfer_cohort_id'); }
}
