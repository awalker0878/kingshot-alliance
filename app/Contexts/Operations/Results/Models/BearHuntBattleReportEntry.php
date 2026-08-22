<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class BearHuntBattleReportEntry extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['report_id', 'player_id', 'reported_rank', 'damage_points'];

    protected function casts(): array
    {
        return ['reported_rank' => 'integer', 'damage_points' => 'integer'];
    }
}
