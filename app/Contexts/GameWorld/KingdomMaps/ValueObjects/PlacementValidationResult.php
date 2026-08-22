<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\ValueObjects;

final readonly class PlacementValidationResult
{
    /**
     * @param  list<array{code: string, message: string, object_key?: string}>  $violations
     * @param  list<array{code: string, message: string, object_key?: string}>  $warnings
     * @param  list<array{code: string, message: string, object_key?: string}>  $suggestions
     */
    public function __construct(
        public array $violations = [],
        public array $warnings = [],
        public array $suggestions = [],
    ) {}

    public function valid(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return array{
     *     violations: list<array{code: string, message: string, object_key?: string}>,
     *     warnings: list<array{code: string, message: string, object_key?: string}>,
     *     suggestions: list<array{code: string, message: string, object_key?: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
        ];
    }
}
