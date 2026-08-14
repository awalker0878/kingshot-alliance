<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventRosterType;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventRoster extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'parent_id', 'key', 'name_key', 'name', 'roster_type', 'assignment_group', 'capacity', 'sort_order', 'settings',
 'created_by_player_id', 'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'roster_type' => EventRosterType::class,
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class, 'occurrence_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function members(): HasMany { return $this->hasMany(EventRosterMember::class, 'roster_id')->orderBy('slot_number')->orderBy('assigned_at'); }
    public function createdByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'created_by_player_id'); }
    public function updatedByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'updated_by_player_id'); }
}
