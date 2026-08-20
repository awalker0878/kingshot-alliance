<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use InvalidArgumentException;

final readonly class ActionReceipt
{
    public const SESSION_KEY = 'actionReceipt';

    /** @param array<string, int|string|float> $parameters */
    private function __construct(
        public string $code,
        public array $parameters,
        public string $tone,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{2,119}$/', $code) !== 1) {
            throw new InvalidArgumentException('Action receipt codes must be stable kebab-case identifiers.');
        }

        if (! in_array($tone, ['success', 'warning', 'info'], true)) {
            throw new InvalidArgumentException('Action receipt tone is unsupported.');
        }
    }

    /** @param array<string, int|string|float> $parameters */
    public static function success(string $code, array $parameters = []): self
    {
        return new self($code, $parameters, 'success');
    }

    /** @param array<string, int|string|float> $parameters */
    public static function warning(string $code, array $parameters = []): self
    {
        return new self($code, $parameters, 'warning');
    }

    /** @return array{code: string, parameters: array<string, int|string|float>, tone: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'parameters' => $this->parameters,
            'tone' => $this->tone,
        ];
    }
}
