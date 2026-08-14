<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventResult extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['occurrence_id','outcome','score','opponent_score','rank','metrics','notes','recorded_by_player_id','recorded_at'];
    protected function casts(): array { return ['score'=>'integer','opponent_score'=>'integer','rank'=>'integer','metrics'=>'array','recorded_at'=>'datetime']; }
    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class, 'occurrence_id'); }
}
