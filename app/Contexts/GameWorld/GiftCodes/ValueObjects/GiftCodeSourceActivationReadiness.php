<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeSourceActivationReadiness
{
    /** @param array<string,array{ready:bool,message:string}> $checks */
    public function __construct(public array $checks) {}

    public function ready(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check['ready']) {
                return false;
            }
        }

        return true;
    }

    /** @return array{ready:bool,checks:array<string,array{ready:bool,message:string}>} */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready(),
            'checks' => $this->checks,
        ];
    }
}
