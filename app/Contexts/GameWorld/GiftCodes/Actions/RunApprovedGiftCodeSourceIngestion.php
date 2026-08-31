<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionSweep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RunApprovedGiftCodeSourceIngestion
{
    public function __construct(
        private GiftCodeSourceAdapterRegistry $adapters,
        private IngestApprovedGiftCodeObservation $ingest,
    ) {}

    public function handle(
        int $sourceLimit = 10,
        ?string $afterSourceId = null,
        ?string $sourceKey = null,
    ): GiftCodeIngestionSweep {
        $startedAt = hrtime(true);
        $sourceLimit = max(1, min(100, $sourceLimit));
        if (! (bool) config('game_world.gift_codes.approved_source_ingestion', false)) {
            return $this->result($startedAt);
        }

        $rows = GiftCodeSourceRegistry::query()
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->whereNull('revoked_at')
            ->when($afterSourceId !== null && $afterSourceId !== '', static fn (Builder $query) => $query->where('id', '>', $afterSourceId))
            ->when($sourceKey !== null && $sourceKey !== '', static fn (Builder $query) => $query->where('source_key', $sourceKey))
            ->orderBy('id')
            ->limit($sourceLimit + 1)
            ->get();
        $truncated = $rows->count() > $sourceLimit;
        $sources = $rows->take($sourceLimit)->values();
        $examined = 0;
        $accepted = 0;
        $duplicates = 0;
        $quarantined = 0;
        $failedSources = 0;
        $observationLimit = max(1, min(500, (int) config('game_world.gift_codes.ingestion_batch_size', 100)));

        foreach ($sources as $source) {
            $run = GiftCodeIngestionRun::query()->create([
                'gift_code_source_id' => (string) $source->id,
                'status' => 'running',
                'source_cursor' => $source->ingestion_cursor,
                'started_at' => now(),
            ]);
            $source->forceFill(['last_ingestion_attempt_at' => now()])->save();

            try {
                $adapter = $this->adapters->find($source->adapter_key);
                if ($adapter === null) {
                    throw new \RuntimeException('No registered adapter matches this approved source.');
                }
                $page = $adapter->acquire($source, $source->ingestion_cursor, $observationLimit);
                if (count($page->observations) > $observationLimit) {
                    throw new \UnexpectedValueException('The source adapter exceeded the bounded observation limit.');
                }

                $runExamined = 0;
                $runAccepted = 0;
                $runDuplicates = 0;
                $runQuarantined = 0;
                $runFailureCode = null;
                $runFailureMessage = null;
                foreach ($page->observations as $observation) {
                    $runExamined++;
                    try {
                        $result = $this->ingest->handle((string) $source->id, $observation);
                        $runAccepted += $result['accepted'] ? 1 : 0;
                        $runDuplicates += $result['duplicate'] ? 1 : 0;
                        $runQuarantined += $result['quarantined'] ? 1 : 0;
                    } catch (Throwable $exception) {
                        report($exception);
                        $runQuarantined++;
                        $runFailureCode ??= $this->observationFailureCode($exception);
                        $runFailureMessage ??= $this->observationFailureMessage(
                            $observation,
                            $runExamined,
                            $exception,
                        );
                    }
                }

                $status = $runQuarantined > 0 ? 'completed_with_quarantine' : 'completed';
                $run->forceFill([
                    'status' => $status,
                    'result_cursor' => $page->nextCursor,
                    'examined_count' => $runExamined,
                    'accepted_count' => $runAccepted,
                    'duplicate_count' => $runDuplicates,
                    'quarantined_count' => $runQuarantined,
                    'failure_code' => $runFailureCode,
                    'failure_message' => $runFailureMessage,
                    'completed_at' => now(),
                ])->save();
                $source->forceFill([
                    'ingestion_cursor' => $page->nextCursor,
                    'last_ingestion_success_at' => now(),
                    'last_ingestion_failure_code' => null,
                    'last_ingestion_error' => null,
                ])->save();
                $examined += $runExamined;
                $accepted += $runAccepted;
                $duplicates += $runDuplicates;
                $quarantined += $runQuarantined;
            } catch (Throwable $exception) {
                report($exception);
                $failedSources++;
                $failureCode = $this->failureCode($exception);
                $message = mb_substr($exception->getMessage(), 0, 2000);
                $run->forceFill([
                    'status' => 'failed',
                    'failure_code' => $failureCode,
                    'failure_message' => $message,
                    'completed_at' => now(),
                ])->save();
                $source->forceFill([
                    'last_ingestion_failure_at' => now(),
                    'last_ingestion_failure_code' => $failureCode,
                    'last_ingestion_error' => $message,
                ])->save();
            }
        }

        $last = $sources->last();
        $next = $truncated && $last instanceof GiftCodeSourceRegistry ? (string) $last->id : null;
        $result = $this->result(
            $startedAt,
            $sources->count(),
            $examined,
            $accepted,
            $duplicates,
            $quarantined,
            $failedSources,
            $next,
        );
        Log::info('gift_codes.approved_source_ingestion_sweep', $result->toArray());

        return $result;
    }

    private function failureCode(Throwable $exception): string
    {
        return match (true) {
            str_contains($exception->getMessage(), 'No registered adapter') => 'adapter_unavailable',
            $exception instanceof \UnexpectedValueException => 'unsupported_source_format',
            default => 'source_retrieval_failed',
        };
    }

    private function observationFailureCode(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \UnexpectedValueException => 'unsupported_observation_format',
            $exception instanceof \Illuminate\Validation\ValidationException => 'observation_policy_rejected',
            default => 'observation_ingestion_failed',
        };
    }

    private function observationFailureMessage(
        GiftCodeIngestionObservation $observation,
        int $position,
        Throwable $exception,
    ): string {
        return mb_substr(sprintf(
            'Observation %d (%s, %s, evidence %s): %s',
            $position,
            trim($observation->code),
            $observation->assertion,
            $observation->rawEvidenceRef,
            $exception->getMessage(),
        ), 0, 2000);
    }

    private function result(
        int $startedAt,
        int $sourceCount = 0,
        int $examined = 0,
        int $accepted = 0,
        int $duplicates = 0,
        int $quarantined = 0,
        int $failedSources = 0,
        ?string $nextSourceCursor = null,
    ): GiftCodeIngestionSweep {
        return new GiftCodeIngestionSweep(
            $sourceCount,
            $examined,
            $accepted,
            $duplicates,
            $quarantined,
            $failedSources,
            $nextSourceCursor,
            (int) round((hrtime(true) - $startedAt) / 1_000_000),
        );
    }
}
