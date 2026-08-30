<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeReference;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSubmissionResult;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SubmitGiftCode
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private ReconcileGiftCodeStatus $reconcile,
    ) {}

    /**
     * @param array{code: string, source_type?: string, source_label?: string|null, source_url?: string|null, expires_at?: string|null, expiry_precision?: string|null, expiry_timezone?: string|null} $attributes
     */
    public function handle(PlayerReference $actor, array $attributes): GiftCodeSubmissionResult
    {
        return DB::transaction(function () use ($actor, $attributes): GiftCodeSubmissionResult {
            $code = trim($attributes['code']);
            if (! preg_match('/^[A-Za-z0-9_-]{3,64}$/', $code)) {
                throw ValidationException::withMessages([
                    'code' => 'Use 3–64 letters, numbers, dashes, or underscores.',
                ]);
            }

            $normalized = Str::upper($code);
            $source = GiftCodeSource::tryFrom($attributes['source_type'] ?? GiftCodeSource::Manual->value)
                ?? GiftCodeSource::Manual;
            if (! in_array($source, [GiftCodeSource::Manual, GiftCodeSource::Community], true)) {
                throw ValidationException::withMessages([
                    'source_type' => 'Registered Gift Code evidence can only be created through a platform-approved source.',
                ]);
            }

            $claimedExpiry = $this->date($attributes['expires_at'] ?? null);
            $expiryPrecision = $claimedExpiry === null ? null : $this->precision($attributes['expiry_precision'] ?? null);
            $expiryTimezone = $claimedExpiry === null ? null : $this->optional($attributes['expiry_timezone'] ?? null);
            $sourceLabel = $this->optional($attributes['source_label'] ?? null);
            $sourceUrl = $this->optional($attributes['source_url'] ?? null);

            $giftCode = GiftCode::query()->firstOrCreate(['normalized_code' => $normalized], [
                    'code' => $code,
                    'normalized_code' => $normalized,
                    'created_by_player_id' => $actor->playerId,
                    'status' => GiftCodeStatus::Pending,
                    'status_revision' => 0,
                    'status_reason_code' => 'awaiting_verified_evidence',
                    'status_evidence_ids' => [],
                    'status_changed_at' => now(),
                    'status_derived_at' => now(),
                    'discovered_at' => now(),
                    'expires_at' => null,
                    'expires_precision' => null,
                    'expires_revision' => 0,
                ]);
            $created = $giftCode->wasRecentlyCreated;

            $fingerprint = hash('sha256', implode('|', [
                $actor->playerId,
                $source->value,
                mb_strtolower($sourceLabel ?? ''),
                mb_strtolower($sourceUrl ?? ''),
                $claimedExpiry?->toIso8601String() ?? '',
                $expiryPrecision ?? '',
                mb_strtolower($expiryTimezone ?? ''),
            ]));
            $provenance = GiftCodeProvenance::query()->firstOrCreate([
                'gift_code_id' => (string) $giftCode->id,
                'fingerprint' => $fingerprint,
            ], [
                'submitted_by_player_id' => $actor->playerId,
                'registered_source_id' => null,
                'source_type' => $source,
                'source_label' => $sourceLabel,
                'source_url' => $sourceUrl,
                'assertion' => 'available',
                'assertion_payload' => null,
                'claimed_expires_at' => $claimedExpiry,
                'expiry_precision' => $expiryPrecision,
                'expiry_timezone' => $expiryTimezone,
                'published_at' => null,
                'evidence_classification' => GiftCodeEvidenceClassification::CommunityClaim,
                'verification_state' => GiftCodeEvidenceVerificationState::Unverified,
                'source_version' => null,
                'retrieval_version' => null,
                'parser_version' => null,
                'content_fingerprint' => null,
                'raw_evidence_ref' => null,
                'observed_at' => now(),
            ]);

            $metadata = [
                'version' => 1,
                'gift_code_id' => (string) $giftCode->id,
                'code' => (string) $giftCode->code,
                'normalized_code' => $normalized,
                'status' => $giftCode->status->value,
                'status_revision' => $giftCode->status_revision,
                'source_type' => $source->value,
                'claimed_expires_at' => $claimedExpiry?->toIso8601String(),
                'created' => $created,
                'duplicate_detected' => ! $created,
                'provenance_added' => $provenance->wasRecentlyCreated,
                'provenance_id' => (string) $provenance->id,
                'verification_state' => $provenance->verification_state->value,
            ];
            $this->audit->record('game_world.gift_code_submitted', $actor, $giftCode, null, $metadata);
            $this->outbox->record(
                $created ? 'gift_code.created' : 'gift_code.provenance_added',
                null,
                $giftCode,
                $metadata,
                null,
                'player:'.$actor->playerId,
            );
            $this->reconcile->handle((string) $giftCode->id, $actor);

            return new GiftCodeSubmissionResult(
                new GiftCodeReference(
                    (string) $giftCode->id,
                    $giftCode->code,
                    $giftCode->status,
                    $giftCode->expires_at,
                ),
                ! $created,
                $provenance->wasRecentlyCreated,
            );
        });
    }

    private function optional(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function date(?string $value): ?Carbon
    {
        $value = $this->optional($value);

        return $value === null ? null : Carbon::parse($value)->endOfDay();
    }

    private function precision(?string $value): string
    {
        return match ($value) {
            'instant', 'minute', 'hour', 'day' => $value,
            default => 'day',
        };
    }
}
