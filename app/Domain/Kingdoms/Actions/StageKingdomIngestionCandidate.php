<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Kingdoms\Services\KingdomIngestionMutationState;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class StageKingdomIngestionCandidate
{
    private const MAX_POWER = '9223372036854775807';

    public function __construct(
        private KingdomIngestionMutationState $mutations,
        private KingdomIngestionAdapterRegistry $adapters,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $record */
    public function handle(string $subscriptionId, string $batchId, array $record): KingdomIngestionCandidate
    {
        return DB::transaction(function () use ($subscriptionId, $batchId, $record): KingdomIngestionCandidate {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $subscription = $context->subscription;
            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->whereKey($batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRunnable($subscription, $batch, $context->alliance);
            $adapter = $this->approvedAdapter($subscription);
            $normalized = $adapter->normalize($record);
            $targetKind = $this->targetKind($normalized['target_kind'] ?? null, $adapter);
            $stableGameId = $this->nullableText($normalized['stable_game_id'] ?? null, 100, 'stable_game_id');
            $sourceRecordId = $this->nullableText($normalized['source_record_id'] ?? null, 191, 'source_record_id');
            $capturedAt = $this->capturedAt($normalized['captured_at'] ?? null);
            $payload = $normalized['payload'] ?? null;

            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'payload' => 'The source adapter must return a normalized payload object.',
                ]);
            }

            $canonicalPayload = $this->canonicalPayload($targetKind, $payload);
            ksort($canonicalPayload);
            $payloadHash = hash('sha256', json_encode($canonicalPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identityHash = hash('sha256', json_encode([
                'alliance_id' => (string) $subscription->alliance_id,
                'subscription_id' => (string) $subscription->id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'target_kind' => $targetKind->value,
                'source_record_id' => $sourceRecordId,
                'captured_at' => $capturedAt->format('Y-m-d\TH:i:s.u\Z'),
                'stable_game_id' => $stableGameId,
                'payload_hash' => $payloadHash,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $state = $stableGameId === null
                ? KingdomIngestionCandidateState::Quarantined
                : KingdomIngestionCandidateState::Pending;
            $quarantineCode = $stableGameId === null ? 'missing_stable_game_id' : null;

            $candidate = KingdomIngestionCandidate::query()->firstOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'identity_hash' => $identityHash,
                ],
                [
                    'batch_id' => $batch->id,
                    'alliance_id' => $subscription->alliance_id,
                    'kingdom_id' => $subscription->kingdom_id,
                    'target_kind' => $targetKind,
                    'stable_game_id' => $stableGameId,
                    'source_record_id' => $sourceRecordId,
                    'captured_at' => $capturedAt,
                    'normalized_payload' => $canonicalPayload,
                    'payload_hash' => $payloadHash,
                    'state' => $state,
                    'quarantine_code' => $quarantineCode,
                ],
            );

            if ($candidate->wasRecentlyCreated) {
                $batch->increment('records_received');
                $batch->increment('records_staged');
                if ($state === KingdomIngestionCandidateState::Quarantined) {
                    $batch->increment('records_quarantined');
                }

                $metadata = [
                    'subscription_id' => (string) $subscription->id,
                    'batch_id' => (string) $batch->id,
                    'candidate_id' => (string) $candidate->id,
                    'target_kind' => $targetKind->value,
                    'state' => $state->value,
                    'quarantine_code' => $quarantineCode,
                    'adapter_key' => $subscription->adapter_key,
                    'adapter_version' => $subscription->adapter_version,
                    'payload_hash' => $payloadHash,
                    'origin' => 'system',
                ];

                $event = $state === KingdomIngestionCandidateState::Quarantined
                    ? 'kingdoms.ingestion_candidate_quarantined'
                    : 'kingdoms.ingestion_candidate_staged';
                $this->outbox->record(
                    $event,
                    (string) $context->alliance->id,
                    $candidate,
                    $metadata,
                    $event.':'.$candidate->id,
                );
            }

            return $candidate->refresh();
        });
    }

    private function assertRunnable(
        KingdomIngestionSubscription $subscription,
        KingdomIngestionBatch $batch,
        Alliance $alliance,
    ): void {
        if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
            throw ValidationException::withMessages(['subscription' => 'The ingestion subscription is not active.']);
        }

        if ($batch->state !== KingdomIngestionBatchState::Pending) {
            throw ValidationException::withMessages(['batch' => 'Candidates can only be staged into a pending ingestion batch.']);
        }

        if ($alliance->kingdom_id === null || (string) $alliance->kingdom_id !== (string) $subscription->kingdom_id) {
            throw ValidationException::withMessages([
                'subscription' => 'Ingestion is blocked because the alliance Kingdom no longer matches the subscription context.',
            ]);
        }

        if ((string) $batch->alliance_id !== (string) $subscription->alliance_id
            || (string) $batch->kingdom_id !== (string) $subscription->kingdom_id
            || $batch->adapter_key !== $subscription->adapter_key
            || $batch->adapter_version !== $subscription->adapter_version) {
            throw ValidationException::withMessages(['batch' => 'The ingestion batch context does not match its subscription.']);
        }
    }

    private function approvedAdapter(KingdomIngestionSubscription $subscription): KingdomIngestionAdapter
    {
        $adapter = $this->adapters->require($subscription->adapter_key);
        if ($adapter->version() !== $subscription->adapter_version) {
            throw ValidationException::withMessages([
                'subscription' => 'The configured source adapter version is no longer approved.',
            ]);
        }

        return $adapter;
    }

    private function targetKind(mixed $value, KingdomIngestionAdapter $adapter): KingdomIngestionTargetKind
    {
        $kind = $value instanceof KingdomIngestionTargetKind
            ? $value
            : (is_string($value) ? KingdomIngestionTargetKind::tryFrom($value) : null);

        if ($kind === null || ! in_array($kind, $adapter->supportedTargetKinds(), true)) {
            throw ValidationException::withMessages([
                'target_kind' => 'The source adapter returned an unsupported ingestion target kind.',
            ]);
        }

        return $kind;
    }

    private function capturedAt(mixed $value): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages(['captured_at' => 'A source capture time is required.']);
        }

        try {
            $capturedAt = Carbon::parse($value)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages(['captured_at' => 'The source capture time is invalid.']);
        }

        if ($capturedAt->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'captured_at' => 'The source capture time cannot be more than five minutes in the future.',
            ]);
        }

        return $capturedAt;
    }

    /** @param array<string|int, mixed> $payload @return array<string, mixed> */
    private function canonicalPayload(KingdomIngestionTargetKind $kind, array $payload): array
    {
        return match ($kind) {
            KingdomIngestionTargetKind::PlayerSnapshot => $this->playerPayload($payload),
            KingdomIngestionTargetKind::AllianceObservation => $this->alliancePayload($payload),
        };
    }

    /** @param array<string|int, mixed> $payload @return array<string, mixed> */
    private function playerPayload(array $payload): array
    {
        $this->assertOnlyKeys($payload, ['observed_name', 'power', 'progression_level', 'observed_alliance_tag']);

        return [
            'observed_name' => $this->requiredText($payload['observed_name'] ?? null, 160, 'observed_name'),
            'power' => $this->power($payload['power'] ?? null, false),
            'progression_level' => $this->nullableText($payload['progression_level'] ?? null, 64, 'progression_level'),
            'observed_alliance_tag' => $this->nullableText($payload['observed_alliance_tag'] ?? null, 32, 'observed_alliance_tag'),
        ];
    }

    /** @param array<string|int, mixed> $payload @return array<string, mixed> */
    private function alliancePayload(array $payload): array
    {
        $this->assertOnlyKeys($payload, ['observed_name', 'observed_tag', 'power', 'member_count']);

        return [
            'observed_name' => $this->requiredText($payload['observed_name'] ?? null, 160, 'observed_name'),
            'observed_tag' => $this->nullableText($payload['observed_tag'] ?? null, 32, 'observed_tag'),
            'power' => $this->power($payload['power'] ?? null, true),
            'member_count' => $this->memberCount($payload['member_count'] ?? null),
        ];
    }

    /** @param array<string|int, mixed> $payload @param list<string> $allowed */
    private function assertOnlyKeys(array $payload, array $allowed): void
    {
        foreach (array_keys($payload) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'payload' => 'The source payload contains a field that is not approved for this target kind.',
                ]);
            }
        }
    }

    private function requiredText(mixed $value, int $max, string $field): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'The normalized source field must be text.']);
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'The normalized source text is missing or too long.']);
        }

        return $value;
    }

    private function nullableText(mixed $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'The normalized source field must be text.']);
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'The normalized source text is too long.']);
        }

        return $value;
    }

    private function power(mixed $value, bool $nullable): ?string
    {
        if ($value === null && $nullable) {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages(['power' => 'Normalized power must be an unsigned integer value.']);
        }

        $value = (string) $value;
        if (preg_match('/^\d{1,19}$/', $value) !== 1) {
            throw ValidationException::withMessages(['power' => 'Normalized power must contain 1-19 digits.']);
        }

        $canonical = ltrim($value, '0');
        $canonical = $canonical === '' ? '0' : $canonical;
        if (strlen($canonical) > strlen(self::MAX_POWER)
            || (strlen($canonical) === strlen(self::MAX_POWER) && strcmp($canonical, self::MAX_POWER) > 0)) {
            throw ValidationException::withMessages(['power' => 'Normalized power exceeds the supported signed 64-bit range.']);
        }

        return $canonical;
    }

    private function memberCount(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0 || $value > 1000000) {
            throw ValidationException::withMessages([
                'member_count' => 'Normalized member count must be between 0 and 1,000,000.',
            ]);
        }

        return $value;
    }
}
