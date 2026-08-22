<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

final readonly class ExtractedFieldCandidate
{
    /**
     * @param  array{left:int,top:int,width:int,height:int}|null  $boundingBox
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $fieldKey,
        public int $rowOrdinal,
        public string $rawText,
        public string $normalizedValue,
        public string $dataType,
        public float $confidence,
        public ?array $boundingBox = null,
        public array $warnings = [],
    ) {}
}
