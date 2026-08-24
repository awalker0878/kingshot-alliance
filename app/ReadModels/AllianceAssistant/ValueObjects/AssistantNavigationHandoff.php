<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\ValueObjects;

final readonly class AssistantNavigationHandoff
{
    public function __construct(
        public string $labelKey,
        public string $href,
    ) {}

    /** @return array{kind:string,labelKey:string,href:string} */
    public function toArray(): array
    {
        return [
            'kind' => 'navigation',
            'labelKey' => $this->labelKey,
            'href' => $this->href,
        ];
    }
}
