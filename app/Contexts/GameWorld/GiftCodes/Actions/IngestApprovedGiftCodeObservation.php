<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class IngestApprovedGiftCodeObservation
{
    public function __construct(
        private ReconcileGiftCodeStatus $trust,
        private ReconcileGiftCodeFacts $facts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @return array{gift_code_id:string,provenance_id:string,accepted:bool,duplicate:bool,quarantined:bool} */
    public function handle(GiftCodeSourceRegistry $source, GiftCodeIngestionObservation $observation): array
    {
        abort_unless((bool) config('game_world.gift_codes.approved_source_ingestion', false), 404);
        if (! $source->is_active || ! $source->ingestion_enabled || $source->revoked_at !== null) {
            throw ValidationException::withMessages(['source' => 'The registered Gift Code source is not authorized for ingestion.']);
        }

        $code = trim($observation->code);
        if (! preg_match('/^[A-Za-z0-9_-]{3,64}$/', $code)) {
            throw ValidationException::withMessages(['code' => 'The approved source produced an unsupported Gift Code format.']);
        }
        if (! in_array($observation->assertion, ['available', 'invalid', 'expires', 'reward', 'applicability'], true)) {
            throw ValidationException::withMessages(['assertion' => 'The approved source produced an unsupported assertion.']);
        }
        $this->assertSourceUrl($source, $observation->sourceUrl);

        $policy = $source->provenance_policy ?? [];
        $verified = ($policy['auto_verify'] ?? false) === true && $observation->verificationPassed;
        $verificationState = $verified
            ? GiftCodeEvidenceVerificationState::Verified
            : GiftCodeEvidenceVerificationState::Quarantined;
        $classification = $source->classification === 'official'
            ? GiftCodeEvidenceClassification::OfficialPublication
            : GiftCodeEvidenceClassification::IndependentObservation;
        $contentFingerprint = preg_match('/^[a-f0-9]{64}$/D', $observation->contentFingerprint) === 1
            ? $observation->contentFingerprint
            : hash('sha256', $observation->contentFingerprint);

        $result = DB::transaction(function () use (
            $source,
            $observation,
            $code,
            $verificationState,
            $classification,
            $contentFingerprint,
        ): array {
            $giftCode = GiftCode::query()->firstOrCreate([
                'normalized_code' => Str::upper($code),
            ], [
                'code' => $code,
                'created_by_player_id' => null,
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
            $fingerprint = hash('sha256', implode('|', [
                (string) $source->id,
                $contentFingerprint,
                $observation->assertion,
                $observation->sourceVersion,
            ]));
            $provenance = GiftCodeProvenance::query()->firstOrCreate([
                'gift_code_id' => (string) $giftCode->id,
                'fingerprint' => $fingerprint,
            ], [
                'submitted_by_player_id' => null,
                'registered_source_id' => (string) $source->id,
                'source_type' => GiftCodeSource::Registered,
                'source_label' => $source->name,
                'source_url' => $observation->sourceUrl,
                'assertion' => $observation->assertion,
                'assertion_payload' => $observation->assertionPayload,
                'claimed_expires_at' => $this->date($observation->claimedExpiresAt),
                'expiry_precision' => $observation->expiryPrecision,
                'expiry_timezone' => $observation->expiryTimezone,
                'published_at' => $this->date($observation->publishedAt),
                'evidence_classification' => $classification,
                'verification_state' => $verificationState,
                'source_version' => $observation->sourceVersion,
                'retrieval_version' => $observation->retrievalVersion,
                'parser_version' => $observation->parserVersion,
                'content_fingerprint' => $contentFingerprint,
                'raw_evidence_ref' => $observation->rawEvidenceRef,
                'observed_at' => now(),
            ]);
            $metadata = [
                'version' => 1,
                'gift_code_id' => (string) $giftCode->id,
                'code' => $giftCode->code,
                'status' => $giftCode->status->value,
                'status_revision' => $giftCode->status_revision,
                'source_type' => GiftCodeSource::Registered->value,
                'registered_source_id' => (string) $source->id,
                'provenance_id' => (string) $provenance->id,
                'verification_state' => $verificationState->value,
            ];
            $this->audit->record('game_world.gift_code_ingested', null, $provenance, null, $metadata);
            if ($provenance->wasRecentlyCreated) {
                $this->outbox->record(
                    $giftCode->wasRecentlyCreated ? 'gift_code.created' : 'gift_code.provenance_added',
                    null,
                    $giftCode,
                    $metadata,
                    null,
                    'gift-code:'.$giftCode->id,
                );
            }

            return [
                'gift_code_id' => (string) $giftCode->id,
                'provenance_id' => (string) $provenance->id,
                'accepted' => $provenance->wasRecentlyCreated && $verificationState === GiftCodeEvidenceVerificationState::Verified,
                'duplicate' => ! $provenance->wasRecentlyCreated,
                'quarantined' => $provenance->wasRecentlyCreated && $verificationState === GiftCodeEvidenceVerificationState::Quarantined,
            ];
        });

        $this->trust->handle($result['gift_code_id']);
        $this->facts->handle($result['gift_code_id']);

        return $result;
    }

    private function assertSourceUrl(GiftCodeSourceRegistry $source, ?string $sourceUrl): void
    {
        $canonical = mb_strtolower(trim((string) $source->canonical_domain));
        $host = is_string($sourceUrl) ? parse_url($sourceUrl, PHP_URL_HOST) : null;
        $host = is_string($host) ? mb_strtolower(rtrim($host, '.')) : null;
        if ($canonical === '' || $host === null || ($host !== $canonical && ! str_ends_with($host, '.'.$canonical))) {
            throw ValidationException::withMessages([
                'source_url' => 'The observation URL does not match the registered canonical source domain.',
            ]);
        }
    }

    private function date(?string $value): ?CarbonImmutable
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : CarbonImmutable::parse($value);
    }
}
