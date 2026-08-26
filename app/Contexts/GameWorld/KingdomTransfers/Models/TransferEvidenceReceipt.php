<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class TransferEvidenceReceipt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'destination_ids' => 'array',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
