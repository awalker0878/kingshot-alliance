<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $gift_code_id
 * @property int $actor_user_id
 * @property GiftCodeModerationAction $action
 * @property string|null $reason
 * @property string|null $previous_status
 * @property string|null $proposed_status
 * @property list<string>|null $evidence_ids
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class GiftCodeModerationDecision extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'actor_user_id',
        'action',
        'reason',
        'previous_status',
        'proposed_status',
        'evidence_ids',
        'metadata',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => GiftCodeModerationAction::class,
            'evidence_ids' => 'array',
            'metadata' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Gift Code moderation decisions are append-only.');
        });

        static::deleting(static function (): never {
            throw new \LogicException('Gift Code moderation decisions are append-only.');
        });
    }
}
