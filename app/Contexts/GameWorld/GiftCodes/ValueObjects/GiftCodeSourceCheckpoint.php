<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeSourceCheckpoint
{
    /** @param array<string,mixed> $providerState */
    public function __construct(
        public ?string $cursor,
        public ?string $retrievalVersion,
        public ?string $providerRequestId,
        public array $providerState = [],
    ) {}

    /** @return array{cursor:string|null,retrievalVersion:string|null,providerRequestId:string|null,providerState:array<string,mixed>} */
    public function toArray(): array
    {
        return [
            'cursor' => $this->cursor,
            'retrievalVersion' => $this->retrievalVersion,
            'providerRequestId' => $this->providerRequestId,
            'providerState' => $this->providerState,
        ];
    }
}
