<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use InvalidArgumentException;

final readonly class BulkItemResult
{
    private function __construct(
        public string $itemId,
        public string $label,
        public string $outcome,
        public string $code,
    ) {
        if (! in_array($outcome, ['succeeded', 'failed', 'skipped'], true)) {
            throw new InvalidArgumentException('Bulk item outcome is unsupported.');
        }

        if (preg_match('/^[a-z][a-z0-9-]{2,119}$/', $code) !== 1) {
            throw new InvalidArgumentException('Bulk item result codes must be stable kebab-case identifiers.');
        }
    }

    public static function succeeded(string $itemId, string $label, string $code = 'completed'): self
    {
        return new self($itemId, $label, 'succeeded', $code);
    }

    public static function failed(string $itemId, string $label, string $code): self
    {
        return new self($itemId, $label, 'failed', $code);
    }

    public static function skipped(string $itemId, string $label, string $code): self
    {
        return new self($itemId, $label, 'skipped', $code);
    }

    /** @return array{itemId: string, label: string, outcome: string, code: string} */
    public function toArray(): array
    {
        return [
            'itemId' => $this->itemId,
            'label' => $this->label,
            'outcome' => $this->outcome,
            'code' => $this->code,
        ];
    }
}
