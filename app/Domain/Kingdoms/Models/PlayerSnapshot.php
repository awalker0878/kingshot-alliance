<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $roster_entry_id
 * @property string $player_id
 * @property string|null $actor_player_id
 * @property string|null $roster_import_id
 * @property string $observed_name
 * @property int $power
 * @property string|null $progression_level
 * @property string|null $observed_alliance_tag
 * @property Carbon $captured_at
 * @property string $source
 * @property string|null $source_subscription_id
 * @property string|null $source_batch_id
 * @property string|null $source_adapter_key
 * @property string|null $source_adapter_version
 * @property string|null $source_record_id
 * @property string|null $source_identity_hash
 * @property string|null $source_payload_hash
 * @property string $idempotency_key
 * @property-read Alliance $alliance
 * @property-read AllianceRosterEntry $rosterEntry
 * @property-read Player $player
 * @property-read Player|null $actor
 * @property-read RosterImport|null $rosterImport
 */
final class PlayerSnapshot extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'roster_entry_id',
        'player_id',
        'actor_player_id',
        'roster_import_id',
        'observed_name',
        'power',
        'progression_level',
        'observed_alliance_tag',
        'captured_at',
        'source',
        'source_subscription_id',
        'source_batch_id',
        'source_adapter_key',
        'source_adapter_version',
        'source_record_id',
        'source_identity_hash',
        'source_payload_hash',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'power' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<AllianceRosterEntry, $this> */
    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(AllianceRosterEntry::class, 'roster_entry_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'actor_player_id');
    }

    /** @return BelongsTo<RosterImport, $this> */
    public function rosterImport(): BelongsTo
    {
        return $this->belongsTo(RosterImport::class, 'roster_import_id');
    }
}
