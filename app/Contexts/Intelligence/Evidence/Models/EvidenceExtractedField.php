<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class EvidenceExtractedField extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'extraction_attempt_id', 'field_key', 'row_ordinal', 'raw_text', 'normalized_value',
        'data_type', 'confidence', 'bounding_box', 'warnings',
    ];

    protected function casts(): array
    {
        return [
            'row_ordinal' => 'integer',
            'confidence' => 'float',
            'bounding_box' => 'array',
            'warnings' => 'array',
        ];
    }
}
