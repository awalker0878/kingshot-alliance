<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class BearHuntResultBaseline extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'player_id', 'source_event_player_result_id', 'baseline_score', 'baseline_rank', 'captured_at',
    ];

    protected function casts(): array
    {
        return ['baseline_score' => 'integer', 'baseline_rank' => 'integer', 'captured_at' => 'datetime'];
    }
}
