<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only evidence describing where a Gift Code was observed.
 *
 * @property string $id
 * @property string $gift_code_id
 * @property string|null $submitted_by_player_id
 * @property string|null $registered_source_id
 * @property GiftCodeSource $source_type
 * @property string|null $source_label
 * @property string|null $source_url
 * @property string $assertion
 * @property array<string, mixed>|null $assertion_payload
 * @property CarbonImmutable|null $claimed_expires_at
 * @property string|null $expiry_precision
 * @property string|null $expiry_timezone
 * @property CarbonImmutable|null $published_at
 * @property GiftCodeEvidenceClassification $evidence_classification
 * @property GiftCodeEvidenceVerificationState $verification_state
 * @property string|null $source_version
 * @property string|null $retrieval_version
 * @property string|null $parser_version
 * @property string|null $content_fingerprint
 * @property string|null $raw_evidence_ref
 * @property CarbonImmutable $observed_at
 * @property string $fingerprint
 * @property-read GiftCode $giftCode
 */
final class GiftCodeProvenance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'submitted_by_player_id',
        'registered_source_id',
        'source_type',
        'source_label',
        'source_url',
        'assertion',
        'assertion_payload',
        'claimed_expires_at',
        'expiry_precision',
        'expiry_timezone',
        'published_at',
        'evidence_classification',
        'verification_state',
        'source_version',
        'retrieval_version',
        'parser_version',
        'content_fingerprint',
        'raw_evidence_ref',
        'observed_at',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => GiftCodeSource::class,
            'assertion_payload' => 'array',
            'claimed_expires_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'evidence_classification' => GiftCodeEvidenceClassification::class,
            'verification_state' => GiftCodeEvidenceVerificationState::class,
            'observed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function registeredSource(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'registered_source_id');
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new \LogicException('Gift Code provenance is append-only.');
        });

        self::deleting(static function (): never {
            throw new \LogicException('Gift Code provenance is append-only.');
        });
    }
}
