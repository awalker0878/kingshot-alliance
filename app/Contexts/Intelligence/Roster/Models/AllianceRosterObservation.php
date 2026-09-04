<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllianceRosterObservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'batch_id', 'alliance_id', 'roster_entry_id', 'observed_name', 'game_player_id',
        'observed_rank', 'power', 'source_metadata',
    ];

    protected function casts(): array
    {
        return ['power' => 'integer', 'source_metadata' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AllianceRosterObservationBatch::class, 'batch_id');
    }
}
