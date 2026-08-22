<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Services\EvidenceRedactor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class EnforceEvidenceRetention
{
    public function __construct(
        private EvidenceRedactor $redactor,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $candidates = GameEvidence::query()
            ->whereNotIn('lifecycle_status', [EvidenceLifecycleStatus::Purged->value])
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $changed = 0;
        foreach ($candidates as $evidenceId) {
            $changed += DB::transaction(function () use ($evidenceId): int {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->first();
                if (! $evidence instanceof GameEvidence) {
                    return 0;
                }

                $committed = EvidenceCommitAttempt::query()
                    ->where('evidence_id', $evidence->id)
                    ->where('status', EvidenceCommitStatus::Succeeded->value)
                    ->exists();
                $status = EvidenceLifecycleStatus::from((string) $evidence->getRawOriginal('lifecycle_status'));
                $age = $this->ageInDays($evidence->created_at);

                if ($committed) {
                    $days = max(1, (int) config('evidence.retention.committed_binary_days', 180));
                    if ($evidence->path !== null && $age >= $days) {
                        $this->redactor->redact($evidence, 'retention_committed_binary');
                        $this->audit->record('evidence.retention_redacted', null, $evidence, (string) $evidence->alliance_id, [
                            'evidence_id' => (string) $evidence->id,
                            'policy_days' => $days,
                        ]);

                        return 1;
                    }

                    return 0;
                }

                $days = match ($status) {
                    EvidenceLifecycleStatus::Rejected,
                    EvidenceLifecycleStatus::Duplicate,
                    EvidenceLifecycleStatus::Deleted => max(1, (int) config('evidence.retention.rejected_days', 14)),
                    EvidenceLifecycleStatus::Failed,
                    EvidenceLifecycleStatus::Unsupported => max(1, (int) config('evidence.retention.failed_days', 30)),
                    default => max(1, (int) config('evidence.retention.uncommitted_days', 90)),
                };
                if ($age < $days || in_array($status, [EvidenceLifecycleStatus::Classifying, EvidenceLifecycleStatus::Extracting, EvidenceLifecycleStatus::Committing], true)) {
                    return 0;
                }

                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'policy_days' => $days,
                    'previous_status' => $status->value,
                ];
                $this->redactor->redact($evidence, 'retention_uncommitted_purge');
                $this->audit->record('evidence.retention_purged', null, $evidence, (string) $evidence->alliance_id, $metadata);
                $evidence->delete();

                return 1;
            });
        }

        return $changed;
    }

    private function ageInDays(mixed $createdAt): int
    {
        return Carbon::parse((string) $createdAt)->diffInDays(now());
    }
}
