<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $created_by_player_id
 * @property string|null $committed_by_player_id
 * @property string $status
 * @property string $schema_version
 * @property string $original_filename
 * @property string $checksum
 * @property int $row_count
 * @property int $create_count
 * @property int $update_count
 * @property int $ambiguous_count
 * @property int $rejected_count
 * @property array<string, mixed> $preview_payload
 * @property array<string, mixed>|null $resolution_payload
 * @property array<string, mixed>|null $committed_summary
 * @property Carbon|null $committed_at
 * @property-read Alliance $alliance
 * @property-read Player $createdBy
 * @property-read Player|null $committedBy
 */
final class RosterImport extends Model
{
    use HasUlids;

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_COMMITTED = 'committed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'kingdom_roster_imports';

    protected $fillable = [
        'alliance_id',
        'created_by_player_id',
        'committed_by_player_id',
        'status',
        'schema_version',
        'original_filename',
        'checksum',
        'row_count',
        'create_count',
        'update_count',
        'ambiguous_count',
        'rejected_count',
        'preview_payload',
        'resolution_payload',
        'committed_summary',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'preview_payload' => 'array',
            'resolution_payload' => 'array',
            'committed_summary' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function committedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'committed_by_player_id');
    }
}
