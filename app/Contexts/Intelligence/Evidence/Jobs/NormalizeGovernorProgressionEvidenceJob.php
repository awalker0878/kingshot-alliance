<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Jobs;

use App\Contexts\Intelligence\Evidence\Actions\NormalizeGovernorProgressionEvidence;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class NormalizeGovernorProgressionEvidenceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [15, 60, 300];

    public function __construct(
        public readonly string $evidenceId,
        public readonly string $extractionAttemptId,
    ) {}

    public function uniqueId(): string
    {
        return $this->evidenceId.':'.$this->extractionAttemptId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('evidence-normalize:'.$this->evidenceId))->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(NormalizeGovernorProgressionEvidence $normalize): void
    {
        $normalize->handle($this->evidenceId, $this->extractionAttemptId);
    }
}
