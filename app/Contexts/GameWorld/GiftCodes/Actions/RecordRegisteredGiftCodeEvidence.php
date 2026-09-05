<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeCuratorGrant;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RecordRegisteredGiftCodeEvidence
{
    public const PARSER_VERSION = 'manual-registered-evidence-v1';

    public function __construct(
        private ReconcileGiftCodeStatus $trust,
        private ReconcileGiftCodeFacts $facts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private PlatformAuthorization $platformAuthorization,
    ) {}

    /**
     * @param array{
     *   source_id:string,
     *   code:string,
     *   assertion:string,
     *   source_url:string,
     *   assertion_payload?:array<string,mixed>|null,
     *   expires_at?:string|null,
     *   expiry_precision?:string|null,
     *   expiry_timezone?:string|null,
     *   published_at?:string|null
     * } $attributes
     * @return array{gift_code_id:string,provenance_id:string,duplicate:bool}
     */
    public function handle(AccountIdentity $actor, array $attributes): array
    {
        $this->authorize($actor);
        abort_unless((bool) config('game_world.gift_codes.moderation', false), 404);

        $source = GiftCodeSourceRegistry::query()->findOrFail(trim($attributes['source_id']));
        $policy = $source->provenance_policy ?? [];
        if (! $source->is_active || $source->revoked_at !== null) {
            throw ValidationException::withMessages(['source_id' => 'The registered Gift Code source is not active.']);
        }
        if (($policy['manual_evidence_allowed'] ?? false) !== true) {
            throw ValidationException::withMessages(['source_id' => 'This registered source is not approved for curated manual evidence.']);
        }
        if (($policy['auto_verify'] ?? false) === true) {
            throw ValidationException::withMessages(['source_id' => 'Manual-evidence sources cannot use automatic verification.']);
        }

        $code = trim($attributes['code']);
        if (preg_match('/^[A-Za-z0-9_-]{3,64}$/D', $code) !== 1) {
            throw ValidationException::withMessages(['code' => 'Use 3–64 letters, numbers, dashes, or underscores.']);
        }
        $assertion = trim($attributes['assertion']);
        if (! in_array($assertion, ['available', 'invalid', 'expires', 'reward', 'applicability'], true)) {
            throw ValidationException::withMessages(['assertion' => 'Choose a supported registered-source assertion.']);
        }
        $sourceUrl = trim($attributes['source_url']);
        $this->assertSourceUrl($source, $sourceUrl);
        $claimedExpiry = $this->date($attributes['expires_at'] ?? null);
        $publishedAt = $this->date($attributes['published_at'] ?? null);
        $expiryPrecision = $claimedExpiry === null
            ? null
            : $this->precision($attributes['expiry_precision'] ?? null);
        $expiryTimezone = $claimedExpiry === null
            ? null
            : $this->optional($attributes['expiry_timezone'] ?? null, 80);
        $payload = $attributes['assertion_payload'] ?? null;
        if ($payload !== null && ! is_array($payload)) {
            throw ValidationException::withMessages(['assertion_payload' => 'Assertion payload must be an object.']);
        }
        if (in_array($assertion, ['reward', 'applicability'], true) && $payload === null) {
            throw ValidationException::withMessages(['assertion_payload' => 'Reward and applicability evidence require a structured assertion payload.']);
        }

        $contentFingerprint = hash('sha256', json_encode([
            'source_id' => (string) $source->id,
            'code' => Str::upper($code),
            'assertion' => $assertion,
            'source_url' => $sourceUrl,
            'assertion_payload' => $payload,
            'expires_at' => $claimedExpiry?->toIso8601String(),
            'expiry_precision' => $expiryPrecision,
            'expiry_timezone' => $expiryTimezone,
            'published_at' => $publishedAt?->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        $classification = $source->classification === 'official'
            ? GiftCodeEvidenceClassification::OfficialPublication
            : GiftCodeEvidenceClassification::IndependentObservation;

        $result = DB::transaction(function () use (
            $actor,
            $source,
            $code,
            $assertion,
            $sourceUrl,
            $payload,
            $claimedExpiry,
            $expiryPrecision,
            $expiryTimezone,
            $publishedAt,
            $contentFingerprint,
            $classification,
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
                $assertion,
                'manual-verified',
            ]));
            $provenance = GiftCodeProvenance::query()->firstOrCreate([
                'gift_code_id' => (string) $giftCode->id,
                'fingerprint' => $fingerprint,
            ], [
                'submitted_by_player_id' => null,
                'registered_source_id' => (string) $source->id,
                'source_type' => GiftCodeSource::Registered,
                'source_label' => $source->name,
                'source_url' => $sourceUrl,
                'assertion' => $assertion,
                'assertion_payload' => $payload,
                'claimed_expires_at' => $claimedExpiry,
                'expiry_precision' => $expiryPrecision,
                'expiry_timezone' => $expiryTimezone,
                'published_at' => $publishedAt,
                'evidence_classification' => $classification,
                'verification_state' => GiftCodeEvidenceVerificationState::Verified,
                'source_version' => 'manual-evidence:'.substr($contentFingerprint, 0, 24),
                'retrieval_version' => 'curator-confirmed',
                'parser_version' => self::PARSER_VERSION,
                'content_fingerprint' => $contentFingerprint,
                'raw_evidence_ref' => $sourceUrl,
                'observed_at' => now(),
            ]);

            $metadata = [
                'version' => 1,
                'gift_code_id' => (string) $giftCode->id,
                'code' => $giftCode->code,
                'source_type' => GiftCodeSource::Registered->value,
                'registered_source_id' => (string) $source->id,
                'provenance_id' => (string) $provenance->id,
                'verification_state' => GiftCodeEvidenceVerificationState::Verified->value,
                'manual_evidence' => true,
            ];
            $this->audit->record('game_world.gift_code_registered_evidence_recorded', $actor, $provenance, null, $metadata);
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
                'duplicate' => ! $provenance->wasRecentlyCreated,
            ];
        });

        $this->trust->handle($result['gift_code_id'], $actor);
        $this->facts->handle($result['gift_code_id'], $actor);

        return $result;
    }

    private function authorize(AccountIdentity $actor): void
    {
        $authorized = $actor->emailVerified
            && $actor->multiFactorConfirmed
            && (
                $this->platformAuthorization->allows($actor)
                || GiftCodeCuratorGrant::activeForUserId($actor->userId)
            );
        if (! $authorized) {
            throw new AuthorizationException('MFA-protected Gift Code curator access is required.');
        }
    }

    private function assertSourceUrl(GiftCodeSourceRegistry $source, string $sourceUrl): void
    {
        if (mb_strlen($sourceUrl) > 2048) {
            throw ValidationException::withMessages(['source_url' => 'The evidence URL is too long.']);
        }
        $canonical = mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.'));
        $scheme = parse_url($sourceUrl, PHP_URL_SCHEME);
        $host = parse_url($sourceUrl, PHP_URL_HOST);
        $host = is_string($host) ? mb_strtolower(rtrim($host, '.')) : null;
        if ($scheme !== 'https'
            || $canonical === ''
            || $host === null
            || ($host !== $canonical && ! str_ends_with($host, '.'.$canonical))) {
            throw ValidationException::withMessages([
                'source_url' => 'The evidence URL must use HTTPS on the registered canonical source domain.',
            ]);
        }
    }

    private function date(?string $value): ?CarbonImmutable
    {
        $value = $this->optional($value, 120);
        if ($value === null) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Provide a valid evidence date or timestamp.']);
        }
    }

    private function precision(?string $value): string
    {
        return match ($value) {
            'instant', 'minute', 'hour', 'day' => $value,
            default => 'day',
        };
    }

    private function optional(?string $value, int $maximum): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value === '') {
            return null;
        }
        if ($value !== null && mb_strlen($value) > $maximum) {
            throw ValidationException::withMessages(['value' => 'A manual evidence field exceeded its maximum length.']);
        }

        return $value;
    }
}
