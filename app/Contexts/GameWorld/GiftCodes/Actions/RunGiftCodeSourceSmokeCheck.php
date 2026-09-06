<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Exceptions\GiftCodeSourceAcquisitionException;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSmokeCheck;
use App\Contexts\GameWorld\GiftCodes\Services\EvaluateGiftCodeSourceActivationReadiness;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Throwable;

final readonly class RunGiftCodeSourceSmokeCheck
{
    public function __construct(
        private GiftCodeSourceAdapterRegistry $adapters,
        private EvaluateGiftCodeSourceActivationReadiness $readiness,
        private PlatformAuthorization $platformAuthorization,
        private AuditRecorder $audit,
    ) {}

    /** @return array<string,mixed> */
    public function handle(AccountIdentity $actor, string $sourceId): array
    {
        abort_unless($this->platformAuthorization->allows($actor), 403);
        $source = GiftCodeSourceRegistry::query()->with('subscriptions')->findOrFail($sourceId);
        $startedAt = hrtime(true);
        $readiness = $this->readiness->forSource($source);
        $status = 'failed';
        $observationCount = 0;
        $retrievalVersion = null;
        $providerRequestId = null;
        $failureCode = null;
        $failureMessage = null;

        try {
            $adapter = $this->adapters->find($source->adapter_key);
            if ($adapter === null) {
                throw new \RuntimeException('No installed adapter matches this configured source.');
            }
            if (! $readiness->ready()) {
                throw new \RuntimeException('Source activation-readiness checks are not complete.');
            }

            // Deliberately discard the page. Adapters are read-only transports; no provenance,
            // cursor, trust or catalogue state is written by a smoke check.
            $page = $adapter->acquire($source, null, 1);
            if (count($page->observations) > 1) {
                throw new \UnexpectedValueException('Source adapter exceeded the smoke-check observation bound.');
            }
            $observationCount = count($page->observations);
            $retrievalVersion = $page->retrievalVersion;
            $providerRequestId = $page->providerRequestId;
            $status = 'passed';
        } catch (Throwable $exception) {
            report($exception);
            $failureCode = $exception instanceof GiftCodeSourceAcquisitionException
                ? $exception->failureCode
                : match (true) {
                    str_contains($exception->getMessage(), 'No installed adapter') => 'adapter_unavailable',
                    str_contains($exception->getMessage(), 'activation-readiness') => 'activation_not_ready',
                    $exception instanceof \UnexpectedValueException => 'adapter_contract_failed',
                    default => 'smoke_check_failed',
                };
            $failureMessage = mb_substr($exception->getMessage(), 0, 2000);
        }

        $pushStatus = $this->pushStatus($source);
        $check = GiftCodeSourceSmokeCheck::query()->create([
            'gift_code_source_id' => (string) $source->id,
            'adapter_key' => $source->adapter_key,
            'status' => $status,
            'readiness' => $readiness->toArray(),
            'observation_count' => $observationCount,
            'retrieval_version' => $retrievalVersion,
            'provider_request_id' => $providerRequestId,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'push_status' => $pushStatus,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'checked_by_user_id' => $actor->userId,
            'checked_at' => now(),
        ]);

        $metadata = [
            'source_id' => (string) $source->id,
            'source_key' => $source->source_key,
            'smoke_check_id' => (string) $check->id,
            'status' => $status,
            'failure_code' => $failureCode,
            'push_status' => $pushStatus,
        ];
        $this->audit->record('game_world.gift_code_source.smoke_checked', $actor, $source, null, $metadata);

        return $metadata + [
            'duration_ms' => $check->duration_ms,
            'observation_count' => $observationCount,
            'retrieval_version' => $retrievalVersion,
            'provider_request_id' => $providerRequestId,
        ];
    }

    private function pushStatus(GiftCodeSourceRegistry $source): string
    {
        if (! $source->push_enabled) {
            return 'not_requested';
        }
        if ($source->subscriptions->contains(static fn ($subscription): bool => $subscription->status === 'active')) {
            return 'active';
        }
        if ($source->subscriptions->contains(static fn ($subscription): bool => $subscription->status === 'pending')) {
            return 'pending';
        }

        return 'not_ready';
    }
}
