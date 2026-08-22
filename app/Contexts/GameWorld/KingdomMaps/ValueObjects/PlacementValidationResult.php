<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\ValueObjects;

final readonly class PlacementValidationResult
{
    /**
     * @param list<array{code:string,message:string,object_key?:string}> $violations
     * @param list<array{code:string,message:string,object_key?:string}> $warnings
     * @param list<array{code:string,message:string,object_key?:string}> $suggestions
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

    /** @return array{violations:array,warnings:array,suggestions:array} */
    public function toArray(): array
    {
        return ['violations' => $this->violations, 'warnings' => $this->warnings, 'suggestions' => $this->suggestions];
    }
}
