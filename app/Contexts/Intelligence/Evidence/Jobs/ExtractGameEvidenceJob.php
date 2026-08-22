<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Jobs;

use App\Contexts\Intelligence\Evidence\Actions\ExtractGameEvidence;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ExtractGameEvidenceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [15, 60, 300];

    public function __construct(
        public readonly string $evidenceId,
        public readonly string $classificationAttemptId,
    ) {}

    public function uniqueId(): string
    {
        return $this->evidenceId.':'.$this->classificationAttemptId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('evidence-extract:'.$this->evidenceId))->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(ExtractGameEvidence $extract): void
    {
        $extract->handle($this->evidenceId, $this->classificationAttemptId);
    }
}
