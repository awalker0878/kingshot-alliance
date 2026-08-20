<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

abstract class Controller
{
    /**
     * @param array<string, int|string|float> $parameters
     * @return array{code: string, parameters: array<string, int|string|float>, tone: string}
     */
    final protected function receipt(string $code, array $parameters = []): array
    {
        return ActionReceipt::success($code, $parameters)->toArray();
    }
}
