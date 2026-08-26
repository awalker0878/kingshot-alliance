<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class TransferEvidenceReviewKingdom extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_transfer_review_kingdoms';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kingdom_number' => 'integer',
            'ordinal' => 'integer',
        ];
    }
}
