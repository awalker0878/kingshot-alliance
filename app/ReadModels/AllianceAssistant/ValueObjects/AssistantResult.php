<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\ValueObjects;

use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;

final readonly class AssistantResult
{
    /**
     * @param list<AssistantEvidence> $evidence
     * @param list<array{title:string,startsAt:string}> $ambiguity
     * @param list<string> $suggestedQuestions
     */
    public function __construct(
        public AssistantIntent $intent,
        public AssistantStatus $status,
        public string $answer,
        public array $evidence = [],
        public array $ambiguity = [],
        public array $suggestedQuestions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $classifications = [];
        foreach ($this->evidence as $evidence) {
            $classifications[$evidence->classification->value] = true;
        }

        return [
            'intent' => $this->intent->value,
            'status' => $this->status->value,
            'answer' => $this->answer,
            'classifications' => array_keys($classifications),
            'evidence' => array_map(
                static fn (AssistantEvidence $evidence): array => $evidence->toArray(),
                $this->evidence,
            ),
            'citations' => $this->status === AssistantStatus::Answered
                ? array_map(
                    static fn (AssistantEvidence $evidence): array => $evidence->citation(),
                    $this->evidence,
                )
                : [],
            'ambiguity' => $this->ambiguity === [] ? null : $this->ambiguity,
            'suggestedQuestions' => $this->suggestedQuestions,
        ];
    }
}
