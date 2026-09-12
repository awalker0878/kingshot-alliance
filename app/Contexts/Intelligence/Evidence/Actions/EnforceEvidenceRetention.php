<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Services\EvidenceRedactor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class EnforceEvidenceRetention
{
    private const COMMIT_TABLES = [
        'evidence_commit_attempts',
        'evidence_transfer_commit_attempts',
        'evidence_governor_progression_commit_attempts',
        'evidence_spatial_commit_attempts',
    ];

    private const ACTIVE_STATUSES = [
        EvidenceLifecycleStatus::Classifying->value,
        EvidenceLifecycleStatus::Extracting->value,
        EvidenceLifecycleStatus::Committing->value,
    ];

    public function __construct(
        private EvidenceRedactor $redactor,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $now = CarbonImmutable::now('UTC');
        $candidates = $this->eligible($now)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $changed = 0;
        foreach ($candidates as $evidenceId) {
            try {
                $changed += DB::transaction(function () use ($evidenceId, $now): int {
                    $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->first();
                    if (! $evidence instanceof GameEvidence) {
                        return 0;
                    }

                    $committed = GameEvidence::query()
                        ->whereKey($evidenceId)
                        ->where($this->successfulCommit(...))
                        ->exists();
                    $status = EvidenceLifecycleStatus::from((string) $evidence->getRawOriginal('lifecycle_status'));
                    $days = $this->days($status, $committed);
                    if (CarbonImmutable::parse((string) $evidence->created_at)->isAfter($now->subDays($days))
                        || in_array($status->value, self::ACTIVE_STATUSES, true)) {
                        return 0;
                    }

                    if ($committed) {
                        if ($evidence->path !== null) {
                            $this->redactor->redact($evidence, 'retention_committed_binary');
                            $metadata = [
                                'evidence_id' => (string) $evidence->id,
                                'policy_days' => $days,
                            ];
                            $this->audit->record('evidence.retention_redacted', null, $evidence, (string) $evidence->alliance_id, $metadata);
                            $this->outbox->record('evidence.retention_redacted', (string) $evidence->alliance_id, $evidence, $metadata);

                            return 1;
                        }

                        return 0;
                    }

                    $metadata = [
                        'evidence_id' => (string) $evidence->id,
                        'policy_days' => $days,
                        'previous_status' => $status->value,
                    ];
                    $this->redactor->redact($evidence, 'retention_uncommitted_purge');
                    $this->audit->record('evidence.retention_purged', null, $evidence, (string) $evidence->alliance_id, $metadata);
                    $this->outbox->record('evidence.retention_purged', (string) $evidence->alliance_id, $evidence, $metadata);
                    $evidence->delete();

                    return 1;
                });
            } catch (Throwable $exception) {
                $evidence = GameEvidence::query()->find($evidenceId);
                if ($evidence instanceof GameEvidence) {
                    $metadata = [
                        'evidence_id' => $evidenceId,
                        'failure_type' => $exception::class,
                    ];
                    $this->audit->record('evidence.retention_failed', null, $evidence, (string) $evidence->alliance_id, $metadata);
                    $this->outbox->record('evidence.retention_failed', (string) $evidence->alliance_id, $evidence, $metadata);
                }
            }
        }

        return $changed;
    }

    /** @return Builder<GameEvidence> */
    private function eligible(CarbonImmutable $now): Builder
    {
        return GameEvidence::query()
            ->whereNotIn('lifecycle_status', self::ACTIVE_STATUSES)
            ->where(function (Builder $query) use ($now): void {
                $query->where(function (Builder $committed) use ($now): void {
                    $committed->where($this->successfulCommit(...))
                        ->whereNotNull('path')
                        ->where('created_at', '<=', $now->subDays($this->days(EvidenceLifecycleStatus::Committed, true)));
                })->orWhere(function (Builder $uncommitted) use ($now): void {
                    $uncommitted->whereNot($this->successfulCommit(...))
                        ->where(function (Builder $due) use ($now): void {
                            $due->where(function (Builder $deleted) use ($now): void {
                                $deleted->where('lifecycle_status', EvidenceLifecycleStatus::Deleted->value)
                                    ->where('created_at', '<=', $now->subDays($this->days(EvidenceLifecycleStatus::Deleted, false)));
                            })->orWhere(function (Builder $failed) use ($now): void {
                                $failed->whereIn('lifecycle_status', [EvidenceLifecycleStatus::Failed->value, EvidenceLifecycleStatus::Unsupported->value])
                                    ->where('created_at', '<=', $now->subDays($this->days(EvidenceLifecycleStatus::Failed, false)));
                            })->orWhere(function (Builder $other) use ($now): void {
                                $other->whereNotIn('lifecycle_status', [EvidenceLifecycleStatus::Deleted->value, EvidenceLifecycleStatus::Failed->value, EvidenceLifecycleStatus::Unsupported->value])
                                    ->where('created_at', '<=', $now->subDays($this->days(EvidenceLifecycleStatus::Uploaded, false)));
                            });
                        });
                });
            });
    }

    /** @param Builder<GameEvidence> $query */
    private function successfulCommit(Builder $query): void
    {
        foreach (self::COMMIT_TABLES as $table) {
            $query->orWhereExists(static function (QueryBuilder $commit) use ($table): void {
                $commit->selectRaw('1')->from($table)
                    ->whereColumn($table.'.evidence_id', 'game_evidence.id')
                    ->where('status', EvidenceCommitStatus::Succeeded->value);
            });
        }
    }

    private function days(EvidenceLifecycleStatus $status, bool $committed): int
    {
        if ($committed) {
            return max(1, (int) config('evidence.retention.committed_binary_days', 180));
        }

        return match ($status) {
            EvidenceLifecycleStatus::Deleted => max(1, (int) config('evidence.retention.deleted_days', 14)),
            EvidenceLifecycleStatus::Failed,
            EvidenceLifecycleStatus::Unsupported => max(1, (int) config('evidence.retention.failed_days', 30)),
            default => max(1, (int) config('evidence.retention.uncommitted_days', 90)),
        };
    }
}
