<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftCodeSourceSyncState extends Model
{
    use HasUlids;

    protected $table = 'gift_code_source_sync_states';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_source_id',
        'sync_mode',
        'latest_observed_provider_id',
        'committed_high_water',
        'candidate_high_water',
        'active_sync_since_id',
        'active_page_token',
        'backfill_page_token',
        'backfill_boundary_provider_id',
        'http_etag',
        'http_last_modified',
        'last_not_modified_at',
        'last_head_poll_at',
        'last_reconciliation_at',
        'last_backfill_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'last_not_modified_at' => 'immutable_datetime',
            'last_head_poll_at' => 'immutable_datetime',
            'last_reconciliation_at' => 'immutable_datetime',
            'last_backfill_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'gift_code_source_id');
    }
}
