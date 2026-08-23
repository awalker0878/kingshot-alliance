<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class EvidenceReviewRow extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'review_id', 'row_ordinal', 'source_rank_field_id', 'source_name_field_id', 'source_damage_field_id',
        'player_id', 'player_name', 'reported_rank', 'damage_points', 'included', 'rank_corrected',
        'name_corrected', 'damage_corrected', 'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'row_ordinal' => 'integer', 'reported_rank' => 'integer', 'damage_points' => 'integer',
            'included' => 'boolean', 'rank_corrected' => 'boolean', 'name_corrected' => 'boolean', 'damage_corrected' => 'boolean',
        ];
    }
}
