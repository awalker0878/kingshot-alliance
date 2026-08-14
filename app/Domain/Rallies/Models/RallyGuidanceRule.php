<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Models;

use App\Domain\Alliances\Models\Alliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $effective_from @property Carbon|null $effective_until */
final class RallyGuidanceRule extends Model
{
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['alliance_id','name','infantry_percent','cavalry_percent','archer_percent','hero_recommendations','lead_requirements','joiner_guidance','source','rationale','effective_from','effective_until','is_active','created_by_player_id','updated_by_player_id'];
    protected function casts(): array { return ['infantry_percent'=>'integer','cavalry_percent'=>'integer','archer_percent'=>'integer','hero_recommendations'=>'array','effective_from'=>'date','effective_until'=>'date','is_active'=>'boolean']; }
    /** @return BelongsTo<Alliance,$this> */
    public function alliance(): BelongsTo { return $this->belongsTo(Alliance::class); }
}
